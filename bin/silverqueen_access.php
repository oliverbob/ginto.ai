<?php
/**
 * SilverQueen access audit — who can actually open /silverqueen right now, and why.
 *
 * The console is whitelisted to paid-up Pro Trader members (P5,000/mo + 12% VAT =
 * P5,600 at checkout) plus the operator accounts. Everyone else must get a 404. This
 * script answers that question against the live database, so the guard can be verified
 * without logging in as anybody:
 *
 *   php bin/silverqueen_access.php            # every account that currently has a seat
 *   php bin/silverqueen_access.php 123        # explain the verdict for one user id
 *   php bin/silverqueen_access.php --suspect  # rows that LOOK active but grant nothing
 *
 * Read-only: it never writes to the database.
 */

$root = dirname(__DIR__);
chdir($root);
if (!defined('ROOT_PATH'))    define('ROOT_PATH', $root);
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', $root . '/storage');

require $root . '/vendor/autoload.php';
try {
    if (class_exists('Dotenv\\Dotenv')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
} catch (\Throwable $e) {}

use Ginto\Core\Database;

const PRO_PLANS = ['academy_pro'];

$db  = Database::getInstance();
$now = date('Y-m-d H:i:s');
$arg = $argv[1] ?? '';

/** Every account holding a current Pro Trader membership — the intended whitelist. */
function entitled($db, string $now): array
{
    return $db->select('user_subscriptions',
        [
            '[>]subscription_plans' => ['plan_id' => 'id'],
            '[>]users'              => ['user_id' => 'id'],
        ],
        [
            'user_subscriptions.user_id(user_id)',
            'users.username(username)',
            'users.email(email)',
            'users.status(ustatus)',
            'subscription_plans.name(plan)',
            'subscription_plans.price_monthly(price)',
            'user_subscriptions.expires_at(expires_at)',
        ],
        [
            'user_subscriptions.status'        => 'active',
            'user_subscriptions.expires_at[>]' => $now,
            'subscription_plans.name'          => PRO_PLANS,
            'subscription_plans.plan_type'     => 'academy',
            'ORDER' => ['user_subscriptions.expires_at' => 'ASC'],
        ]) ?: [];
}

/** Operator/admin accounts, which reach the console through elevation instead. */
function elevated($db): array
{
    return $db->select('users', ['id', 'username', 'email', 'role_id', 'status'], [
        'OR' => ['role_id' => [1, 2], 'username' => 'oliverbob'],
    ]) ?: [];
}

/**
 * Subscription rows that read as "active" at a glance but confer no seat. These are the
 * shapes that used to slip past the old guard — a NULL expiry that never lapsed, or a
 * plan merely re-titled "Pro Trader" — so an empty list here is the clean result.
 */
function suspect($db, string $now): array
{
    $rows = $db->select('user_subscriptions',
        [
            '[>]subscription_plans' => ['plan_id' => 'id'],
            '[>]users'              => ['user_id' => 'id'],
        ],
        [
            'user_subscriptions.user_id(user_id)',
            'users.username(username)',
            'subscription_plans.name(plan)',
            'subscription_plans.display_name(display)',
            'subscription_plans.plan_type(ptype)',
            'subscription_plans.price_monthly(price)',
            'user_subscriptions.expires_at(expires_at)',
        ],
        [
            'user_subscriptions.status' => 'active',
            'OR' => [
                'user_subscriptions.expires_at' => null,
                'subscription_plans.display_name' => 'Pro Trader',
            ],
            'ORDER' => ['user_subscriptions.user_id' => 'ASC'],
        ]) ?: [];

    // Drop the genuinely entitled rows: a real Pro membership also matches the
    // 'Pro Trader' display name, and listing it as a look-alike would be misleading.
    return array_values(array_filter($rows, function ($r) use ($now) {
        $real = in_array((string) $r['plan'], PRO_PLANS, true)
            && (string) $r['ptype'] === 'academy'
            && !empty($r['expires_at']) && (string) $r['expires_at'] > $now;
        return !$real;
    }));
}

if ($arg === '--suspect') {
    $rows = suspect($db, $now);
    echo "Active rows with a NULL expiry or a 'Pro Trader' display name:\n\n";
    foreach ($rows as $r) {
        printf("  user %-6s %-20s plan=%-16s display=%-14s type=%-10s price=%-9s expires=%s\n",
            $r['user_id'], (string) $r['username'], (string) $r['plan'], (string) $r['display'],
            (string) $r['ptype'], (string) $r['price'], $r['expires_at'] ?? 'NULL');
    }
    echo $rows ? "\n" . count($rows) . " row(s) — none of these grant access under the current guard.\n"
               : "  none\n";
    exit(0);
}

if ($arg !== '' && ctype_digit($arg)) {
    $uid  = (int) $arg;
    $user = $db->get('users', ['id', 'username', 'email', 'role_id', 'status'], ['id' => $uid]);
    if (!is_array($user)) { echo "user {$uid}: no such account — 404.\n"; exit(1); }

    $isElevated = in_array((int) $user['role_id'], [1, 2], true)
        || strtolower(trim((string) $user['username'])) === 'oliverbob';
    $pro = array_values(array_filter(entitled($db, $now), fn($r) => (int) $r['user_id'] === $uid));

    printf("user %d (%s <%s>) status=%s role_id=%s\n", $uid, $user['username'], $user['email'],
        $user['status'], $user['role_id']);
    echo "  registered/active : " . ($user['status'] === 'active' ? 'yes' : 'NO') . "\n";
    echo "  elevated (admin)  : " . ($isElevated ? 'yes' : 'no') . "\n";
    echo "  paid-up Pro Trader: " . ($pro ? 'yes, until ' . $pro[0]['expires_at'] : 'no') . "\n";
    echo "  => /silverqueen   : "
       . (($user['status'] === 'active' && ($isElevated || $pro)) ? "ALLOWED\n" : "404 Not Found\n");

    $all = $db->select('user_subscriptions',
        ['[>]subscription_plans' => ['plan_id' => 'id']],
        ['user_subscriptions.status(status)', 'user_subscriptions.expires_at(expires_at)',
         'subscription_plans.name(plan)', 'subscription_plans.display_name(display)',
         'subscription_plans.price_monthly(price)'],
        ['user_subscriptions.user_id' => $uid, 'ORDER' => ['user_subscriptions.id' => 'DESC']]) ?: [];
    echo "\n  subscription history:\n";
    foreach ($all as $s) {
        printf("    %-10s %-16s %-14s price=%-9s expires=%s\n", $s['status'], (string) $s['plan'],
            (string) $s['display'], (string) $s['price'], $s['expires_at'] ?? 'NULL');
    }
    if (!$all) echo "    none\n";
    exit(0);
}

$pro = entitled($db, $now);
echo "Paid-up Pro Trader members with a SilverQueen seat (as of {$now}):\n\n";
foreach ($pro as $r) {
    printf("  user %-6s %-20s %-30s plan=%-14s price=%-9s expires=%s%s\n",
        $r['user_id'], (string) $r['username'], (string) $r['email'], (string) $r['plan'],
        (string) $r['price'], $r['expires_at'],
        $r['ustatus'] === 'active' ? '' : "  (account {$r['ustatus']} — denied)");
}
echo $pro ? '' : "  none\n";

$adm = elevated($db);
echo "\nOperator accounts (reach the console through elevation, not a subscription):\n\n";
foreach ($adm as $a) {
    printf("  user %-6s %-20s %-30s role_id=%-3s %s\n", $a['id'], (string) $a['username'],
        (string) $a['email'], (string) $a['role_id'], (string) $a['status']);
}
echo $adm ? '' : "  none\n";

echo "\nEveryone else gets 404. Run with --suspect to review look-alike rows,"
   . " or with a user id to explain one account.\n";
