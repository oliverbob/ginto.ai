<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * Product photos: taken in once, shrunk once, known by the SHA-256 of what
 * the seller uploaded. The same picture uploaded twice is the same row, and
 * its vision answer is reused (never analysed again).
 */
final class Photos
{
    public const MAX_BYTES = 12 * 1024 * 1024;
    public const MAX_PER_SITE = 12;

    public function __construct(private $db)
    {
    }

    public static function dir(): string
    {
        $d = STORAGE_PATH . '/sites/photos';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    public static function path(string $sha): string
    {
        return self::dir() . '/' . $sha . '.jpg';
    }

    /**
     * Take one uploaded file. Returns its sha256, or throws with words for the seller.
     */
    public function ingest(string $tmpPath): string
    {
        $size = @filesize($tmpPath);
        if (!$size || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Each photo must be under 12 MB.');
        }
        $info = @getimagesize($tmpPath);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new \InvalidArgumentException('Use JPG, PNG or WebP photos.');
        }
        $sha = hash_file('sha256', $tmpPath);
        if ($this->db->has('sites_photos', ['sha256' => $sha]) && is_file(self::path($sha))) {
            return $sha;
        }

        [$w, $h] = self::shrink($tmpPath, $info[2], self::path($sha));
        $this->db->insert('sites_photos', ['sha256' => $sha, 'path' => 'sites/photos/' . $sha . '.jpg', 'width' => $w, 'height' => $h, 'status' => 'pending']);

        return $sha;
    }

    /** Longest edge at most 1024 px, re-encoded as JPEG (which also drops the camera's EXIF, location included). */
    private static function shrink(string $src, int $type, string $dest): array
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        };
        if (!$img) {
            throw new \InvalidArgumentException('That photo could not be read.');
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($src)['Orientation'] ?? 1);
            $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, Config::VISION_MAX_EDGE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // a transparent PNG lands on white
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($out, $dest, 85);
        @chmod($dest, 0664);

        return [$nw, $nh];
    }

    /**
     * The dominant colours of a photo, without AI: k-means over a 48 px thumbnail.
     * @return list<string> hex colours, most common first
     */
    public static function palette(string $sha, int $k = 5): array
    {
        $img = @imagecreatefromjpeg(self::path($sha));
        if (!$img) {
            return [];
        }
        $t = imagecreatetruecolor(48, 48);
        imagecopyresampled($t, $img, 0, 0, 0, 0, 48, 48, imagesx($img), imagesy($img));
        $px = [];
        for ($y = 0; $y < 48; $y++) {
            for ($x = 0; $x < 48; $x++) {
                $c = imagecolorat($t, $x, $y);
                $px[] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
            }
        }
        // Seeds spread through the pixels, then a few rounds of k-means.
        $centres = [];
        for ($i = 0; $i < $k; $i++) {
            $centres[] = $px[(int) floor(($i + 0.5) * count($px) / $k)];
        }
        $groups = [];
        for ($round = 0; $round < 8; $round++) {
            $groups = array_fill(0, $k, []);
            foreach ($px as $p) {
                $best = 0;
                $bd = PHP_INT_MAX;
                foreach ($centres as $i => $c) {
                    $d = ($p[0] - $c[0]) ** 2 + ($p[1] - $c[1]) ** 2 + ($p[2] - $c[2]) ** 2;
                    if ($d < $bd) {
                        $bd = $d;
                        $best = $i;
                    }
                }
                $groups[$best][] = $p;
            }
            foreach ($groups as $i => $g) {
                if ($g) {
                    $n = count($g);
                    $centres[$i] = [array_sum(array_column($g, 0)) / $n, array_sum(array_column($g, 1)) / $n, array_sum(array_column($g, 2)) / $n];
                }
            }
        }
        $order = array_keys($groups);
        usort($order, static fn ($a, $b) => count($groups[$b]) <=> count($groups[$a]));

        return array_values(array_map(static fn ($i) => sprintf('#%02x%02x%02x', ...array_map('intval', $centres[$i])), array_filter($order, static fn ($i) => $groups[$i] !== [])));
    }
}
