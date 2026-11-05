<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Services\MallCommerceService;

/**
 * A SilverQueen shop, given its storefront here.
 *
 * Owner, 2026-10-18: SilverQueen's mall IS this mall (silverqueen.pro/mall,
 * formerly ginto.ai/mall), and SilverQueen was made to bring people to it --
 * so a member who opens a shop there is extending their profile to the Ginto
 * Mall program, which its Mall terms say from version 1.1. SilverQueen's
 * GintoProvision posts each unpaired shop here; this finds the seller's
 * account or makes one, gives it a storefront, and answers with the user id,
 * which SilverQueen keeps as the pair (its listings then show on that
 * storefront through SilverQueenShelf).
 *
 * <h3>Which account</h3>
 *
 *  1. `ginto_user_id`, when SilverQueen knows the member uses another address
 *     here (owner's mall.ginto_users list) -- it must exist.
 *  2. Otherwise the account with that email.
 *  3. Otherwise a new one, made the way the Academy makes its learners: no
 *     order row (User::register books a P250 Starter order nobody bought), a
 *     password nobody knows -- "Forgot password" is the way in if the member
 *     ever wants one -- and the same default upline sign-up uses.
 *
 * An existing storefront is never changed: a seller who already set theirs up
 * here keeps every word of it. Signed with SQ_RELAY_SECRET, the same as the
 * PayMongo relay ({@see SqRelayController::authentic()}).
 */
class SqMallRelayController
{
    /** Sign-up's own fallback upline when nobody referred the member. */
    private const DEFAULT_UPLINE = 2;

    public function provision()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input') ?: '';

        if (!(new SqRelayController())->authentic($raw)) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorised']);
            exit();
        }

        $in = json_decode($raw, true);

        if (!is_array($in) || (int) ($in['sq_shop_id'] ?? 0) <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'bad_body']);
            exit();
        }

        try {
            echo json_encode($this->provisionShop($in));
        } catch (\Throwable $e) {
            error_log('sq mall relay: ' . $e->getMessage());
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        }

        exit();
    }

    /**
     * Every seller here with something on sale, for SilverQueen to give a shop
     * of its own (owner, 2026-10-25: "Sync everything."). The other direction
     * of provision(): SilverQueen matches the account by email, opens or finds
     * the member's shop there and pairs it, and from then on its mall shows
     * these listings and takes their orders. Signed, because emails are in it.
     */
    public function sellers()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input') ?: '';

        if (!(new SqRelayController())->authentic($raw)) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorised']);
            exit();
        }

        $rows = Database::getInstance()->query(
            "SELECT u.id AS user_id, LOWER(u.email) AS email, u.fullname,
                    s.slug, s.display_name, s.description, s.logo_image, s.banner_image,
                    COUNT(p.id) AS listings
               FROM products p
               JOIN users u ON u.id = p.seller_id
          LEFT JOIN seller_storefronts s ON s.user_id = u.id
              WHERE p.status = 'published' AND p.is_visible = 1
           GROUP BY u.id, u.email, u.fullname, s.slug, s.display_name, s.description, s.logo_image, s.banner_image
           ORDER BY u.id"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['sellers' => array_map(static fn (array $r): array => [
            'user_id'  => (int) $r['user_id'],
            'email'    => (string) $r['email'],
            // The shop's name only -- never the account's full name, which is a
            // legal name and has no place on a shop a member trades from.
            'name'     => (string) ($r['display_name'] ?? ''),
            'slug'     => (string) ($r['slug'] ?? ''),
            'about'    => (string) ($r['description'] ?? ''),
            'logo'     => $r['logo_image'],
            'banner'   => $r['banner_image'],
            'listings' => (int) $r['listings'],
        ], $rows)]);
        exit();
    }

    private function provisionShop(array $in): array
    {
        $db       = Database::getInstance();
        $commerce = new MallCommerceService();

        $email  = strtolower(trim((string) ($in['email'] ?? '')));
        $name   = trim((string) ($in['seller_name'] ?? '')) ?: trim((string) ($in['shop_name'] ?? ''));
        $known  = (int) ($in['ginto_user_id'] ?? 0);
        $userId = 0;
        $made   = false;

        if ($known > 0) {
            if (!$db->has('users', ['id' => $known])) {
                throw new \RuntimeException('no Ginto Mall user ' . $known);
            }
            $userId = $known;
        } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $userId = (int) ($db->get('users', 'id', ['email' => $email]) ?: 0);

            if ($userId === 0) {
                $userId = $this->createAccount($name ?: 'SilverQueen seller', $email);
                $made   = true;
            }
        } else {
            throw new \RuntimeException('no email and no known account');
        }

        $commerce->ensureWalletAccount($userId);

        $existing = $db->get('seller_storefronts', ['slug'], ['user_id' => $userId]);

        if (is_array($existing)) {
            return ['user_id' => $userId, 'slug' => $existing['slug'], 'created_user' => $made, 'created_storefront' => false];
        }

        $shopName = trim((string) ($in['shop_name'] ?? '')) ?: $name;
        $base     = $commerce->sanitizeSlug($shopName) ?: 'store-' . $userId;
        $slug     = $commerce->uniqueStorefrontSlug($base);
        $now      = date('Y-m-d H:i:s');
        $https    = static fn ($u): ?string => is_string($u) && preg_match('#^https://#i', $u) ? substr($u, 0, 255) : null;

        $db->insert('seller_storefronts', [
            'user_id'      => $userId,
            'slug'         => $slug,
            'display_name' => mb_substr($shopName, 0, 191),
            'description'  => trim((string) ($in['about'] ?? '')) ?: 'Official storefront on Ginto Mall',
            'logo_image'   => $https($in['logo'] ?? null),
            'banner_image' => $https($in['banner'] ?? null),
            'is_active'    => 1,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return ['user_id' => $userId, 'slug' => $slug, 'created_user' => $made, 'created_storefront' => true];
    }

    /** The Academy's learner insert, with the sign-up default upline. */
    private function createAccount(string $name, string $email): int
    {
        $db   = Database::getInstance();
        $seed = preg_replace('/[^a-z0-9]+/', '', strtolower(explode('@', $email)[0])) ?: 'seller';
        $username = substr($seed, 0, 18) . mt_rand(100, 9999);

        for ($i = 0; $i < 6 && $db->has('users', ['username' => $username]); $i++) {
            $username = substr($seed, 0, 14) . mt_rand(1000, 999999);
        }

        $db->insert('users', [
            'public_id'     => substr(md5(uniqid((string) mt_rand(), true)), 0, 12),
            'fullname'      => mb_substr($name, 0, 100),
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'status'        => 'active',
            'role_id'       => 5,
            'referrer_id'   => $db->has('users', ['id' => self::DEFAULT_UPLINE]) ? self::DEFAULT_UPLINE : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->id();

        if ($id <= 0) {
            throw new \RuntimeException('account not created');
        }

        return $id;
    }
}
