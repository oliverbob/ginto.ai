<?php

namespace Ginto\Services;

/**
 * The SilverQueen mall listings of a seller who sells in both malls.
 *
 * Owner, 2026-10-18: *"wherever she's posting from or listing from, will be
 * visible in both platforms ... my listing here should be visible there."*
 * SilverQueen (sq.silverqueen.pro, PostgreSQL, the store inside its chat) and
 * this mall share no database, so a storefront here shows the same seller's
 * SilverQueen shelf READ LIVE, and Buy on one of those opens it there --
 * SilverQueen's checkout takes the money. Nothing is copied, so a price edited
 * there is the price shown here on the next read.
 *
 * Who is paired is decided on the SilverQueen side (setting mall.ginto_pairs):
 * this asks by OUR seller id and gets nothing back for anybody unpaired, and
 * nothing for a shop SilverQueen would not let a stranger buy from.
 *
 * Cached on disk for TTL; when stale ONE request refreshes (non-blocking lock)
 * and the rest are served the stale copy. SilverQueen down means the last good
 * shelf, or none -- never an error on a storefront that is otherwise fine.
 */
class SilverQueenShelf
{
    public const ORIGIN = 'https://sq.silverqueen.pro';

    private const TTL = 300;

    /**
     * Shaped like the storefront's PRODUCTS entries, plus `ext_url`.
     * Ids are negative so they can never collide with a product of ours.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forSeller(int $sellerId): array
    {
        if ($sellerId <= 0) {
            return [];
        }

        $out = [];

        foreach (self::read($sellerId) as $it) {
            $url = (string) ($it['url'] ?? '');

            if (!preg_match('#^https://sq\.silverqueen\.pro/i/\d+\.\d+$#', $url)) {
                continue;
            }

            $out[] = [
                'id'       => -abs((int) ($it['id'] ?? 0)),
                'title'    => (string) ($it['title'] ?? ''),
                'price'    => round((float) ($it['price'] ?? 0), 2),
                'currency' => (string) ($it['currency'] ?? 'PHP'),
                'cat'      => null,
                'rating'   => 0,
                'img'      => $it['img'] ?? null,
                'imgs'     => array_values(array_filter((array) ($it['imgs'] ?? []))),
                'desc'     => (string) ($it['desc'] ?? ''),
                'slug'     => '',
                'badge'    => null,
                'seller_slug' => null,
                'seller_name' => null,
                'seller_id'   => $sellerId,
                'ext_url'  => $url,
            ];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private static function read(int $sellerId): array
    {
        $file = self::cacheFile('sq-shelf-' . $sellerId . '.json');
        $kept = null;

        if ($file !== null && is_file($file)) {
            $kept = json_decode((string) @file_get_contents($file), true);

            if (is_array($kept) && time() - (int) ($kept['at'] ?? 0) < self::TTL) {
                return (array) ($kept['items'] ?? []);
            }
        }

        $stale = is_array($kept) ? (array) ($kept['items'] ?? []) : [];
        $lock  = $file === null ? false : @fopen($file . '.lock', 'c');

        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return $stale;
        }

        try {
            $fresh = self::fetch($sellerId);

            if ($fresh === null) {
                return $stale;
            }

            @file_put_contents($file, json_encode(['at' => time(), 'items' => $fresh]), LOCK_EX);

            return $fresh;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<int,array<string,mixed>>|null null when SilverQueen could not be read */
    private static function fetch(int $sellerId): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init(self::ORIGIN . '/api/v1/ginto-mall/sellers/' . $sellerId . '/items');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        $json = is_string($body) && $code === 200 ? json_decode($body, true) : null;
        $items = is_array($json) ? ($json['data']['items'] ?? null) : null;

        return is_array($items) ? $items : null;
    }

    private static function cacheFile(string $name): ?string
    {
        $dir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage') . '/cache';

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        return is_writable($dir) ? $dir . '/' . $name : null;
    }
}
