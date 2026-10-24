<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * The twelve ways a SilverQueen Site can look (owner, 2026-10-24: "12
 * templates to choose (with names) ... platform based templates they love ...
 * but also ... our own organic or generic template naming ... related to
 * throneship and crownings or our platform naming conventions").
 *
 * The platform-like ones carry their own names, not the platforms' (no other
 * company's trademark on a product we sell); the description says what they
 * feel like. Switching costs no AI: the same reviewed words and photos, drawn
 * again.
 *
 * Each: name, line (shown in the picker), hero (how the top is laid out),
 * shop (how products are laid out), palette ("theme" = the AI's colours from
 * the photos, or fixed [bg, fg, accent, muted, card]), font, and css.
 */
final class Templates
{
    public const DEFAULT = 'crowned';

    public const ALL = [
        'channel'   => ['name' => 'Channel', 'line' => 'Video-first, like your favourite channel', 'hero' => 'feature', 'shop' => 'channel',
            'palette' => ['#0f0f0f', '#f1f1f1', '#ff4e45', '#aaaaaa', '#1c1c1c'], 'font' => 'sans'],
        'timeline'  => ['name' => 'Timeline', 'line' => 'A profile with your products as posts', 'hero' => 'profile', 'shop' => 'posts',
            'palette' => ['#f0f2f5', '#1c1e21', '#1b74e4', '#65676b', '#ffffff'], 'font' => 'sans'],
        'reel'      => ['name' => 'Reel', 'line' => 'Full-screen vertical cards you scroll through', 'hero' => 'vertical', 'shop' => 'vertical',
            'palette' => ['#000000', '#ffffff', '#fe2c55', '#a1a1a1', '#121212'], 'font' => 'display'],
        'thread'    => ['name' => 'Thread', 'line' => 'Text-first, black and white, short and sharp', 'hero' => 'centered', 'shop' => 'thread',
            'palette' => ['#ffffff', '#0f1419', '#0f1419', '#536471', '#ffffff'], 'font' => 'sans'],
        'assistant' => ['name' => 'Assistant', 'line' => 'Calm and clear, your shop told as a conversation', 'hero' => 'chat', 'shop' => 'chips',
            'palette' => ['#f7f7f8', '#202123', '#10a37f', '#6e6e80', '#ffffff'], 'font' => 'sans'],
        'prism'     => ['name' => 'Prism', 'line' => 'Soft glowing gradients and floating cards', 'hero' => 'glow', 'shop' => 'glass',
            'palette' => ['#0b0b1a', '#f4f4ff', '#8ab4f8', '#b4b4cc', '#16162b'], 'font' => 'display'],
        'throne'    => ['name' => 'Throne', 'line' => 'Black and gold, made for royalty', 'hero' => 'split', 'shop' => 'grid',
            'palette' => ['#0b0905', '#f3ead2', '#d4af37', '#b9a77a', '#16120a'], 'font' => 'serif'],
        'crowned'   => ['name' => 'Crowned', 'line' => 'A spotlight with a crown on your best product', 'hero' => 'cover', 'shop' => 'grid',
            'palette' => 'theme', 'font' => 'serif'],
        'court'     => ['name' => 'Court', 'line' => 'A gallery mosaic, like the Birthday Court', 'hero' => 'split', 'shop' => 'mosaic',
            'palette' => 'theme', 'font' => 'serif'],
        'reign'     => ['name' => 'Reign', 'line' => 'Editorial luxury: big type, lots of air', 'hero' => 'editorial', 'shop' => 'rows',
            'palette' => ['#f6f1e7', '#1b1814', '#8a6a2f', '#6f665a', '#fffaf1'], 'font' => 'serif'],
        'money'     => ['name' => 'Money Talks', 'line' => 'Bold and high-contrast, offers up front', 'hero' => 'bold', 'shop' => 'grid',
            'palette' => ['#0a0a0a', '#ffffff', '#ffd400', '#bdbdbd', '#161616'], 'font' => 'display'],
        'founders'  => ['name' => 'Founders', 'line' => 'Warm and story-led: your journey and milestones', 'hero' => 'story', 'shop' => 'grid',
            'palette' => ['#fbf4ea', '#3b2a1a', '#b5651d', '#7a6550', '#fffaf3'], 'font' => 'serif'],
    ];

    public static function get(?string $id): array
    {
        $id = isset(self::ALL[$id ?? '']) ? $id : self::DEFAULT;

        return ['id' => $id] + self::ALL[$id];
    }

    /** For the pickers: id => [name, line]. */
    public static function list(): array
    {
        return array_map(static fn (array $t) => ['name' => $t['name'], 'line' => $t['line']], self::ALL);
    }
}
