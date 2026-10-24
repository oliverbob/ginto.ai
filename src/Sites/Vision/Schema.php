<?php

declare(strict_types=1);

namespace Ginto\Sites\Vision;

/** The one answer every vision provider must give, per photo. */
final class Schema
{
    public const PROMPT = 'You look at product photos for an online shop in the Philippines. For EACH photo, in the order given, '
        . 'return what a shop owner would write about it. Answer ONLY with JSON: {"photos":[{"product_type":string,'
        . '"suggested_name":string,"colors":[hex strings like "#a1b2c3"],"material_guess":string,"style_tags":[strings],'
        . '"short_description":string,"photo_quality":"good"|"poor"}]}. short_description is at most 30 words, plain and honest: '
        . 'never invent prices, brands, sizes or stock. Use "poor" when the photo is blurry, dark or the product is hard to see.';

    /** @return array<string,mixed>|null the cleaned answer, or null if it does not fit */
    public static function clean(mixed $a): ?array
    {
        if (!is_array($a)) {
            return null;
        }
        foreach (['product_type', 'suggested_name', 'material_guess', 'short_description'] as $k) {
            if (!isset($a[$k]) || !is_string($a[$k])) {
                return null;
            }
        }
        if (!isset($a['colors'], $a['style_tags']) || !is_array($a['colors']) || !is_array($a['style_tags'])) {
            return null;
        }
        if (!in_array($a['photo_quality'] ?? null, ['good', 'poor'], true)) {
            return null;
        }
        $colors = array_values(array_filter(array_map(static fn ($c) => is_string($c) ? strtolower(trim($c)) : '', $a['colors']),
            static fn ($c) => preg_match('/^#[0-9a-f]{6}$/', $c) === 1));
        $tags = array_values(array_filter(array_map(static fn ($t) => is_string($t) ? mb_substr(trim($t), 0, 24) : '', $a['style_tags'])));

        return [
            'product_type'      => mb_substr(trim($a['product_type']), 0, 60),
            'suggested_name'    => mb_substr(trim($a['suggested_name']), 0, 80),
            'colors'            => array_slice($colors, 0, 6),
            'material_guess'    => mb_substr(trim($a['material_guess']), 0, 60),
            'style_tags'        => array_slice($tags, 0, 8),
            'short_description' => mb_substr(trim($a['short_description']), 0, 300),
            'photo_quality'     => $a['photo_quality'],
        ];
    }
}
