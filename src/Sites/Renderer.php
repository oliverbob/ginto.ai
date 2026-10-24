<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * A site plan into a page.
 *
 * The plan is data (see Builder); every word in it lands escaped, colours are
 * checked as #rrggbb and corrected for contrast, fonts come from a short list.
 * The page is one file with inline CSS: fast on a phone, nothing to break.
 */
final class Renderer
{
    private const FONTS = [
        'serif'   => ['Playfair+Display:wght@600;700', '"Playfair Display", Georgia, serif'],
        'sans'    => ['Inter:wght@600;700', 'Inter, system-ui, sans-serif'],
        'display' => ['Poppins:wght@600;700', 'Poppins, system-ui, sans-serif'],
    ];

    /** @return array<string,mixed>|null */
    public static function clean(mixed $p, int $photos): ?array
    {
        if (!is_array($p) || !is_array($p['brand'] ?? null) || !is_array($p['hero'] ?? null) || !is_array($p['products'] ?? null)) {
            return null;
        }
        $s = static fn ($v, int $max): string => is_string($v) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $v)), 0, $max) : '';
        $hex = static fn ($v, string $d): string => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($v)) ? strtolower(trim($v)) : $d;
        $t = is_array($p['theme'] ?? null) ? $p['theme'] : [];

        $theme = [
            'primary'    => $hex($t['primary'] ?? null, '#7a5600'),
            'accent'     => $hex($t['accent'] ?? null, '#e0b23c'),
            'background' => $hex($t['background'] ?? null, '#fffaf0'),
            'text'       => $hex($t['text'] ?? null, '#1a1f2b'),
            'font'       => isset(self::FONTS[$t['font'] ?? '']) ? $t['font'] : 'serif',
        ];
        if (self::contrast($theme['text'], $theme['background']) < 4.5) {
            $theme['text'] = self::luminance($theme['background']) > 0.4 ? '#141414' : '#f7f7f7';
        }

        $products = [];
        $seen = [];
        foreach ($p['products'] as $prod) {
            $i = is_array($prod) ? (int) ($prod['photo'] ?? -1) : -1;
            if ($i < 0 || $i >= $photos || isset($seen[$i])) {
                continue;
            }
            $seen[$i] = true;
            $products[] = ['photo' => $i, 'name' => $s($prod['name'] ?? '', 80), 'description' => $s($prod['description'] ?? '', 320)];
        }
        // Every photo is on the page, even one the model forgot.
        for ($i = 0; $i < $photos; $i++) {
            if (!isset($seen[$i])) {
                $products[] = ['photo' => $i, 'name' => '', 'description' => ''];
            }
        }

        $highlights = [];
        foreach (array_slice(is_array($p['highlights'] ?? null) ? $p['highlights'] : [], 0, 3) as $h) {
            if (is_array($h) && $s($h['title'] ?? '', 60) !== '') {
                $highlights[] = ['title' => $s($h['title'], 60), 'body' => $s($h['body'] ?? '', 200)];
            }
        }

        return [
            'brand'      => ['name' => $s($p['brand']['name'] ?? '', 60) ?: 'My Shop', 'tagline' => $s($p['brand']['tagline'] ?? '', 120)],
            'theme'      => $theme,
            'hero'       => ['headline' => $s($p['hero']['headline'] ?? '', 90), 'subheadline' => $s($p['hero']['subheadline'] ?? '', 220), 'cta' => $s($p['hero']['cta'] ?? '', 30) ?: 'Shop now'],
            'about'      => ['title' => $s($p['about']['title'] ?? '', 60), 'body' => $s($p['about']['body'] ?? '', 900)],
            'products'   => $products,
            'highlights' => $highlights,
            'closing'    => ['headline' => $s($p['closing']['headline'] ?? '', 90), 'cta' => $s($p['closing']['cta'] ?? '', 30) ?: 'Message us'],
        ];
    }

    /** A plain, honest site from the seller's own words, for when the AI could not give a plan. */
    public static function fallback(string $description, array $photos): array
    {
        $palette = $photos[0]['palette'] ?? [];
        $first = trim(strtok(trim($description), ".\n") ?: 'Welcome');

        return self::clean([
            'brand' => ['name' => mb_substr($first, 0, 40), 'tagline' => ''],
            'theme' => ['primary' => $palette[0] ?? '#7a5600', 'accent' => $palette[1] ?? '#e0b23c', 'background' => '#ffffff', 'text' => '#1a1f2b', 'font' => 'sans'],
            'hero' => ['headline' => mb_substr($first, 0, 80), 'subheadline' => '', 'cta' => 'See our products'],
            'about' => ['title' => 'About us', 'body' => $description],
            'products' => array_map(static fn ($i) => ['photo' => $i, 'name' => (string) ($photos[$i]['vision']['suggested_name'] ?? ''),
                'description' => (string) ($photos[$i]['vision']['short_description'] ?? '')], array_keys($photos)),
            'highlights' => [], 'closing' => ['headline' => 'Thank you for visiting', 'cta' => 'Message us'],
        ], count($photos));
    }

    /**
     * @param list<string> $photoUrls in plan photo order
     * @param array{preview_hours?:int, claim_url?:string, name?:string} $opts
     */
    public static function html(array $plan, array $photoUrls, array $opts = []): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $t = $plan['theme'];
        [$fontCss, $fontStack] = self::FONTS[$t['font']];
        $dark = self::luminance($t['background']) < 0.35;
        $soft = $dark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.04)';
        $line = $dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)';
        $onPrimary = self::contrast('#ffffff', $t['primary']) >= 4.5 ? '#ffffff' : '#141414';
        $preview = isset($opts['preview_hours']);
        $brand = $plan['brand']['name'];
        $heroImg = $photoUrls[$plan['products'][0]['photo'] ?? 0] ?? '';
        // Only a link a visitor can safely follow: the web, a phone number or an e-mail address.
        $contact = (string) ($opts['contact_url'] ?? '#shop');
        if (preg_match('#^(https://|tel:\+?[0-9 ]{6,20}$|mailto:[^\s<>"]+@[^\s<>"]+$|\#)#i', $contact) !== 1) {
            $contact = '#shop';
        }

        $cards = '';
        foreach ($plan['products'] as $p) {
            $url = $photoUrls[$p['photo']] ?? '';
            $cards .= '<article class="card"><div class="ph"><img src="' . $e($url) . '" alt="' . $e($p['name'] ?: $brand) . '" loading="lazy"></div>'
                . '<div class="cb"><h3>' . $e($p['name'] ?: 'New arrival') . '</h3>'
                . ($p['description'] !== '' ? '<p>' . $e($p['description']) . '</p>' : '')
                . '<a class="ask" href="#contact">Ask about this</a></div></article>';
        }
        $highs = '';
        foreach ($plan['highlights'] as $h) {
            $highs .= '<div class="hl"><h3>' . $e($h['title']) . '</h3><p>' . $e($h['body']) . '</p></div>';
        }

        $banner = '';
        if (isset($opts['trial_hours'])) {
            $banner = '<div class="pv"><span><b>Free trial</b> · ' . (int) $opts['trial_hours'] . ' hour' . ((int) $opts['trial_hours'] === 1 ? '' : 's') . ' left</span>'
                . '<a href="' . $e($opts['claim_url'] ?? 'https://silverqueen.pro/sites/me') . '">Go live &rarr;</a></div>';
        } elseif ($preview) {
            $banner = '<div class="pv"><span><b>Free preview</b> · disappears in ' . (int) $opts['preview_hours'] . ' hours</span>'
                . '<a href="' . $e($opts['claim_url'] ?? '/sites/start') . '">Claim your name &rarr;</a></div>';
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . ($preview || isset($opts['trial_hours']) ? '<meta name="robots" content="noindex,nofollow">' : '')
            . '<title>' . $e($brand) . '</title>'
            . '<meta name="description" content="' . $e($plan['brand']['tagline'] ?: $plan['hero']['subheadline']) . '">'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . $fontCss . '&family=Inter:wght@400;500&display=swap">'
            . '<style>:root{--p:' . $t['primary'] . ';--a:' . $t['accent'] . ';--bg:' . $t['background'] . ';--fg:' . $t['text'] . ';--on:' . $onPrimary
            . ';--soft:' . $soft . ';--line:' . $line . '}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--fg);'
            . 'font:16px/1.6 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased}img{display:block;max-width:100%}a{color:inherit}'
            . 'h1,h2,h3,.brand{font-family:' . $fontStack . ';letter-spacing:-.01em;line-height:1.15;margin:0}'
            . '.pv{position:sticky;top:0;z-index:9;display:flex;gap:12px;justify-content:center;align-items:center;flex-wrap:wrap;padding:10px 16px;'
            . 'background:#141414;color:#fff;font-size:14px}.pv a{background:#e0b23c;color:#2a1d05;padding:6px 12px;border-radius:5px;text-decoration:none;font-weight:600}'
            . 'header{display:flex;justify-content:space-between;align-items:center;padding:18px clamp(16px,5vw,64px)}'
            . '.brand{font-size:22px;font-weight:700;color:var(--p)}nav a{margin-left:18px;text-decoration:none;font-size:14px;opacity:.8}'
            . '.hero{display:grid;grid-template-columns:1.1fr 1fr;gap:clamp(20px,4vw,56px);align-items:center;padding:clamp(24px,6vw,72px) clamp(16px,5vw,64px)}'
            . '.hero h1{font-size:clamp(34px,6vw,64px);font-weight:700}.hero p{font-size:18px;opacity:.85;margin:18px 0 26px;max-width:36ch}'
            . '.btn{display:inline-block;background:var(--p);color:var(--on);padding:14px 26px;border-radius:5px;text-decoration:none;font-weight:600}'
            . '.hero .art{aspect-ratio:4/5;border-radius:24px;overflow:hidden;box-shadow:0 30px 60px -30px rgba(0,0,0,.45);background:var(--soft)}'
            . '.hero .art img{width:100%;height:100%;object-fit:cover}.tag{display:inline-block;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:var(--p);margin-bottom:14px}'
            . 'section{padding:clamp(32px,6vw,72px) clamp(16px,5vw,64px)}section>h2{font-size:clamp(26px,4vw,40px);margin-bottom:24px}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px}'
            . '.card{background:var(--soft);border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column}'
            . '.ph{aspect-ratio:1;overflow:hidden}.ph img{width:100%;height:100%;object-fit:cover;transition:transform .5s}.card:hover .ph img{transform:scale(1.04)}'
            . '.cb{padding:16px 18px 20px;display:flex;flex-direction:column;gap:8px;flex:1}.cb h3{font-size:19px}.cb p{margin:0;font-size:15px;opacity:.8}'
            . '.ask{margin-top:auto;align-self:flex-start;font-size:14px;font-weight:600;color:var(--p);text-decoration:none;border-bottom:2px solid var(--a)}'
            . '.about{display:grid;grid-template-columns:1fr 1.4fr;gap:40px;background:var(--soft)}.about p{margin:0;font-size:17px;opacity:.9;white-space:pre-line}'
            . '.hls{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:22px}.hl{border-top:3px solid var(--a);padding-top:14px}.hl h3{font-size:19px;margin-bottom:6px}.hl p{margin:0;opacity:.8}'
            . '.close{text-align:center}.close h2{margin-bottom:22px}footer{padding:28px 16px;text-align:center;font-size:13px;opacity:.6;border-top:1px solid var(--line)}'
            . '@media(max-width:760px){.hero,.about{grid-template-columns:1fr}.about{gap:14px}.about h2{margin:0}.hero .art{order:-1;aspect-ratio:1}nav{display:none}.grid{grid-template-columns:repeat(2,1fr);gap:12px}.cb{padding:12px 12px 14px}.cb h3{font-size:16px}.cb p{font-size:13.5px}}</style></head><body>'
            . $banner
            . '<header><div class="brand">' . $e($brand) . '</div><nav><a href="#shop">Shop</a><a href="#about">About</a><a href="#contact">Contact</a></nav></header>'
            . '<div class="hero"><div>' . ($plan['brand']['tagline'] !== '' ? '<span class="tag">' . $e($plan['brand']['tagline']) . '</span>' : '')
            . '<h1>' . $e($plan['hero']['headline'] ?: $brand) . '</h1>'
            . ($plan['hero']['subheadline'] !== '' ? '<p>' . $e($plan['hero']['subheadline']) . '</p>' : '<p></p>')
            . '<a class="btn" href="#shop">' . $e($plan['hero']['cta']) . '</a></div>'
            . ($heroImg !== '' ? '<div class="art"><img src="' . $e($heroImg) . '" alt=""></div>' : '') . '</div>'
            . '<section id="shop"><h2>Shop</h2><div class="grid">' . $cards . '</div></section>'
            . ($plan['about']['body'] !== '' ? '<section class="about" id="about"><h2>' . $e($plan['about']['title'] ?: 'About us') . '</h2><p>' . $e($plan['about']['body']) . '</p></section>' : '')
            . ($highs !== '' ? '<section><div class="hls">' . $highs . '</div></section>' : '')
            . '<section class="close" id="contact"><h2>' . $e($plan['closing']['headline'] ?: 'Thank you for visiting') . '</h2>'
            . '<a class="btn" href="' . $e($contact) . '"' . (str_starts_with($contact, 'https://') ? ' target="_blank" rel="noopener"' : '') . '>' . $e($plan['closing']['cta']) . '</a></section>'
            . '<footer>&copy; ' . date('Y') . ' ' . $e($brand) . ' &middot; Made with SilverQueen Sites'
            . (!empty($opts['founding']) ? ' &middot; <span style="color:var(--p);font-weight:600">&#127941; Founding Member</span>' : '') . '</footer>'
            . '</body></html>';
    }

    private static function luminance(string $hex): float
    {
        $c = array_map(static function (int $v): float {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
