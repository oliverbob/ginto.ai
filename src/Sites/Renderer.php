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
     * @param array{preview_hours?:int, trial_hours?:int, trial_until?:int, claim_url?:string, contact_url?:string, founding?:bool,
     *              template?:string, videos?:array<int,string>, page?:array} $opts
     */
    public static function html(array $plan, array $photoUrls, array $opts = []): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $tpl = Templates::get($opts['template'] ?? null);
        $t = $plan['theme'];

        // Colours: the template's own fixed set, or the AI's colours from the photos.
        if (is_array($tpl['palette'])) {
            [$bg, $fg, $accent, $muted, $card] = $tpl['palette'];
            $primary = $accent;
        } else {
            $bg = $t['background'];
            $fg = $t['text'];
            $primary = $t['primary'];
            $accent = $t['accent'];
            $dark0 = self::luminance($bg) < 0.35;
            $muted = $dark0 ? 'rgba(255,255,255,.66)' : 'rgba(0,0,0,.58)';
            $card = $dark0 ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.035)';
        }
        $dark = self::luminance($bg) < 0.35;
        $line = $dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.09)';
        $onPrimary = self::contrast('#ffffff', $primary) >= 4.5 ? '#ffffff' : '#141414';
        [$fontCss, $fontStack] = self::FONTS[$tpl['font']] ?? self::FONTS['serif'];

        $brand = $plan['brand']['name'];
        $videos = is_array($opts['videos'] ?? null) ? $opts['videos'] : [];
        $contact = (string) ($opts['contact_url'] ?? '#shop');
        if (preg_match('#^(https://|tel:\+?[0-9 ]{6,20}$|mailto:[^\s<>"]+@[^\s<>"]+$|\#)#i', $contact) !== 1) {
            $contact = '#shop';
        }
        $ext = str_starts_with($contact, 'https://') ? ' target="_blank" rel="noopener"' : '';
        $headline = $plan['hero']['headline'] ?: $brand;
        $sub = $plan['hero']['subheadline'];
        $tag = $plan['brand']['tagline'];
        $cta = $plan['hero']['cta'];
        $first = $plan['products'][0] ?? null;
        $heroImg = $first ? ($photoUrls[$first['photo']] ?? '') : '';
        $img = static fn (string $u, string $alt = '', string $cls = ''): string => $u === '' ? '' : '<img' . ($cls ? ' class="' . $cls . '"' : '') . ' src="' . $e($u) . '" alt="' . $e($alt) . '" loading="lazy">';
        $play = '<span class="play" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>';

        // ---- products, in the template's layout ----
        $items = [];
        foreach ($plan['products'] as $k => $p) {
            $u = $photoUrls[$p['photo']] ?? '';
            $v = $videos[$p['photo']] ?? null;
            $media = $img($u, $p['name'] ?: $brand) . ($v ? $play : '');
            if ($v && preg_match('#^https://#', $v) === 1) {
                $media = '<a class="vid" href="' . $e($v) . '" target="_blank" rel="noopener" aria-label="Play video">' . $media . '</a>';
            }
            $items[] = ['k' => $k, 'media' => $media, 'name' => $p['name'] ?: 'New arrival', 'desc' => $p['description']];
        }
        $ask = '<a class="ask" href="' . $e($contact) . '"' . $ext . '>Ask about this</a>';
        $shop = '';
        foreach ($items as $it) {
            $crown = ($tpl['id'] === 'crowned' && $it['k'] === 0) ? '<span class="crownpick">&#128081; Crowned pick</span>' : '';
            $text = '<h3>' . $e($it['name']) . '</h3>' . ($it['desc'] !== '' ? '<p>' . $e($it['desc']) . '</p>' : '');
            $shop .= match ($tpl['shop']) {
                'posts'    => '<article class="post"><div class="ph"><span class="av">' . $e(mb_substr($brand, 0, 1)) . '</span><b>' . $e($brand) . '</b><small>&middot; Shop</small></div>'
                    . ($it['desc'] !== '' ? '<p class="pt">' . $e($it['desc']) . '</p>' : '') . '<div class="pm">' . $it['media'] . '</div>'
                    . '<div class="pa"><b>' . $e($it['name']) . '</b>' . $ask . '</div></article>',
                'thread'   => '<article class="tw"><span class="av">' . $e(mb_substr($brand, 0, 1)) . '</span><div><b>' . $e($brand) . '</b> <small>@' . $e(strtolower(preg_replace('/[^a-z0-9]/i', '', $brand))) . '</small>'
                    . '<p><b>' . $e($it['name']) . '.</b> ' . $e($it['desc']) . '</p><div class="tm">' . $it['media'] . '</div>' . $ask . '</div></article>',
                'vertical' => '<article class="vc">' . $it['media'] . '<div class="vt">' . $text . $ask . '</div></article>',
                'rows'     => '<article class="row">' . '<div class="rm">' . $it['media'] . '</div><div class="rt"><span class="no">' . sprintf('%02d', $it['k'] + 1) . '</span>' . $text . $ask . '</div></article>',
                'chips'    => '<article class="chip">' . $it['media'] . '<div>' . $text . $ask . '</div></article>',
                'channel'  => '<article class="ch">' . '<div class="cm">' . $it['media'] . '</div><div class="ct"><span class="av">' . $e(mb_substr($brand, 0, 1)) . '</span><div>' . $text . '</div></div></article>',
                default    => '<article class="card">' . $crown . '<div class="cm">' . $it['media'] . '</div><div class="cb">' . $text . $ask . '</div></article>',
            };
        }
        $shopClass = ['grid' => 'g-grid', 'mosaic' => 'g-mosaic', 'glass' => 'g-grid g-glass', 'channel' => 'g-channel', 'posts' => 'g-posts',
            'thread' => 'g-thread', 'vertical' => 'g-vertical', 'rows' => 'g-rows', 'chips' => 'g-chips'][$tpl['shop']] ?? 'g-grid';

        // ---- the top, in the template's layout ----
        $btn = '<a class="btn" href="#shop">' . $e($cta) . '</a>';
        $hero = match ($tpl['hero']) {
            'feature'   => '<section class="h-feature"><div class="fm">' . $img($heroImg) . (($first && isset($videos[$first['photo']])) ? $play : '') . '</div>'
                . '<div class="ft"><h1>' . $e($headline) . '</h1><p>' . $e($sub ?: $tag) . '</p>' . $btn . '</div></section>',
            'profile'   => '<section class="h-profile"><div class="cov">' . $img($heroImg) . '</div><div class="pf"><span class="big-av">' . $e(mb_substr($brand, 0, 1)) . '</span>'
                . '<div><h1>' . $e($brand) . '</h1><p>' . $e($tag ?: $headline) . '</p></div>' . $btn . '</div></section>',
            'vertical'  => '<section class="h-vertical">' . $img($heroImg) . '<div class="vt"><h1>' . $e($headline) . '</h1><p>' . $e($sub) . '</p>' . $btn . '</div></section>',
            'centered'  => '<section class="h-centered"><h1>' . $e($headline) . '</h1><p>' . $e($sub ?: $tag) . '</p>' . $btn . '</section>',
            'chat'      => '<section class="h-chat"><div class="bub q">What do you have for me?</div><div class="bub a"><b>' . $e($brand) . '</b><br>' . $e($sub ?: $headline) . '</div>'
                . '<div class="bub q">Show me</div>' . $btn . '</section>',
            'glow'      => '<section class="h-glow"><div class="orb o1"></div><div class="orb o2"></div><span class="kicker">' . $e($tag ?: $brand) . '</span><h1>' . $e($headline) . '</h1><p>' . $e($sub) . '</p>' . $btn . '</section>',
            'cover'     => '<section class="h-cover">' . $img($heroImg) . '<div class="shade"></div><div class="ct"><span class="kicker">&#128081; ' . $e($tag ?: $brand) . '</span><h1>' . $e($headline) . '</h1><p>' . $e($sub) . '</p>' . $btn . '</div></section>',
            'editorial' => '<section class="h-editorial"><span class="kicker">' . $e($brand) . '</span><h1>' . $e($headline) . '</h1><p>' . $e($sub ?: $tag) . '</p>' . $btn . '</section>',
            'bold'      => '<section class="h-bold"><div><span class="kicker">' . $e($tag ?: 'Now open') . '</span><h1>' . $e($headline) . '</h1><p>' . $e($sub) . '</p>' . $btn . '</div><div class="bm">' . $img($heroImg) . '</div></section>',
            'story'     => '<section class="h-story"><div><span class="kicker">Our story</span><h1>' . $e($headline) . '</h1><p class="lede">' . $e($plan['about']['body'] ?: $sub) . '</p>' . $btn . '</div><div class="sm">' . $img($heroImg) . '</div></section>',
            default     => '<section class="h-split"><div>' . ($tag !== '' ? '<span class="kicker">' . $e($tag) . '</span>' : '') . '<h1>' . $e($headline) . '</h1><p>' . $e($sub) . '</p>' . $btn . '</div>'
                . ($heroImg !== '' ? '<div class="art">' . $img($heroImg) . '</div>' : '') . '</section>',
        };

        $highs = '';
        foreach ($plan['highlights'] as $i => $h) {
            $highs .= '<div class="hl">' . ($tpl['id'] === 'founders' ? '<span class="ms">Milestone ' . ($i + 1) . '</span>' : '') . '<h3>' . $e($h['title']) . '</h3><p>' . $e($h['body']) . '</p></div>';
        }
        $aboutHtml = ($plan['about']['body'] !== '' && $tpl['hero'] !== 'story')
            ? '<section class="about" id="about"><h2>' . $e($plan['about']['title'] ?: 'About us') . '</h2><p>' . $e($plan['about']['body']) . '</p></section>' : '';

        // The member's Page and Store, which the site carries too (owner, 2026-10-24:
        // "the sites should contain details about the page and the store").
        [$storeHtml, $pageHtml] = self::pageAndStore(is_array($opts['page'] ?? null) ? $opts['page'] : [], $e, $brand);

        $banner = '';
        if (isset($opts['trial_hours'])) {
            // The end time, not the hours left: the page is written once and read for hours,
            // and "5 hours left" stayed 5 all afternoon (owner's phone, 2026-10-24).
            $when = '';
            if (!empty($opts['trial_until'])) {
                $end = (new \DateTimeImmutable('@' . (int) $opts['trial_until']))->setTimezone(new \DateTimeZone('Asia/Manila'));
                $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d') === $end->format('Y-m-d');
                $when = 'until ' . $end->format($today ? 'g:i A' : 'M j, g:i A');
            }
            $banner = '<div class="pv"><span><b>Free trial</b> &middot; ' . ($when !== '' ? $when
                    : (int) $opts['trial_hours'] . ' hour' . ((int) $opts['trial_hours'] === 1 ? '' : 's') . ' left') . '</span>'
                . '<a href="' . $e($opts['claim_url'] ?? 'https://silverqueen.pro/sites/me') . '">Go live &rarr;</a></div>';
        } elseif (isset($opts['preview_hours'])) {
            $banner = '<div class="pv"><span><b>Free preview</b> &middot; disappears in ' . (int) $opts['preview_hours'] . ' hours</span>'
                . '<a href="' . $e($opts['claim_url'] ?? '/sites/start') . '">Claim your name &rarr;</a></div>';
        }
        $noindex = isset($opts['trial_hours']) || isset($opts['preview_hours']);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . ($noindex ? '<meta name="robots" content="noindex,nofollow">' : '')
            . '<title>' . $e($brand) . '</title><meta name="description" content="' . $e($tag ?: $sub) . '">'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . $fontCss . '&family=Inter:wght@400;500;600&display=swap">'
            . '<style>:root{--bg:' . $bg . ';--fg:' . $fg . ';--p:' . $primary . ';--a:' . $accent . ';--on:' . $onPrimary . ';--mu:' . $muted . ';--card:' . $card . ';--line:' . $line . '}'
            . self::css($fontStack) . '</style></head><body class="t-' . $e($tpl['id']) . '">'
            . $banner
            . '<header class="top"><div class="brand">' . $e($brand) . '</div><nav><a href="#shop">Shop</a>' . ($storeHtml !== '' ? '<a href="#store">Store</a>' : '')
            . ($pageHtml !== '' ? '<a href="#page">Page</a>' : '') . '<a href="#about">About</a><a href="#contact">Contact</a></nav></header>'
            . $hero
            . '<section id="shop" class="shop"><h2>' . ($tpl['id'] === 'court' ? 'The Court' : ($tpl['id'] === 'channel' ? 'Videos &amp; products' : 'Shop')) . '</h2><div class="' . $shopClass . '">' . $shop . '</div></section>'
            . $storeHtml
            . $aboutHtml
            . ($highs !== '' ? '<section class="hls-wrap"><div class="hls">' . $highs . '</div></section>' : '')
            . $pageHtml
            . '<section class="close" id="contact"><h2>' . $e($plan['closing']['headline'] ?: 'Thank you for visiting') . '</h2>'
            . '<a class="btn" href="' . $e($contact) . '"' . $ext . '>' . $e($plan['closing']['cta']) . '</a></section>'
            . '<footer>&copy; ' . date('Y') . ' ' . $e($brand) . ' &middot; Made with SilverQueen Premium'
            . (!empty($opts['founding']) ? ' &middot; <span class="fb">&#127941; Founding Member</span>' : '') . '</footer>'
            . '</body></html>';
    }

    /**
     * The Store (their real Mall listings, each to its public page) and the Page
     * (their SilverQueen profile, opened in the app), under the site's own brand:
     * a member's profile name is never printed on a public site (an owner's name
     * stays private; a member's nickname is theirs to show in the app). Only https addresses; a
     * member with no shop gets no Store section at all.
     *
     * @return array{0:string,1:string}
     */
    private static function pageAndStore(array $d, callable $e, string $brand): array
    {
        $https = static fn ($u): string => is_string($u) && preg_match('#^https://[^\s"<>]+$#', $u) === 1 ? $u : '';
        $store = '';
        $shop = is_array($d['shop'] ?? null) ? $d['shop'] : null;
        if ($shop && !empty($shop['items'])) {
            $cards = '';
            foreach (array_slice($shop['items'], 0, 8) as $it) {
                $url = $https($it['url'] ?? '');
                $ph = $https($it['photo'] ?? '');
                if ($url === '') {
                    continue;
                }
                $cards .= '<a class="si" href="' . $e($url) . '" target="_blank" rel="noopener">'
                    . '<span class="sp">' . ($ph !== '' ? '<img src="' . $e($ph) . '" alt="" loading="lazy">' : '') . '</span>'
                    . '<b>' . $e($it['name'] ?? '') . '</b><span class="pr">' . $e($it['price'] ?? '') . '</span><span class="buy">Buy</span></a>';
            }
            if ($cards !== '') {
                $store = '<section class="sq-store" id="store"><h2>Our store</h2><p class="sub">On the SilverQueen Mall &middot; ' . $e($shop['name'] ?? '') . '</p>'
                    . '<div class="sg">' . $cards . '</div></section>';
            }
        }
        $page = '';
        $pr = is_array($d['profile'] ?? null) ? $d['profile'] : null;
        if ($pr) {
            $cover = $https($pr['cover'] ?? '');
            $av = $https($pr['avatar'] ?? '');
            $link = $https($pr['url'] ?? '') ?: 'https://sq.silverqueen.pro/';
            $page = '<section class="sq-page" id="page"><div class="pc">' . ($cover !== '' ? '<img src="' . $e($cover) . '" alt="" loading="lazy" style="object-position:'
                . (int) ($pr['cx'] ?? 50) . '% ' . (int) ($pr['cy'] ?? 50) . '%">' : '') . '</div>'
                . '<div class="pw"><span class="pa">' . ($av !== '' ? '<img src="' . $e($av) . '" alt="">' : '') . '</span>'
                . '<div><span class="k">Find us on SilverQueen</span><h2>' . $e($brand) . '</h2><p>Our lives, our news and our store, all in one place.</p></div>'
                . '<a class="btn" href="' . $e($link) . '" target="_blank" rel="noopener">Follow on SilverQueen</a></div></section>';
        }

        return [$store, $page];
    }

    /** One stylesheet for all twelve: shared bones, then each template's own look (body.t-<id>). */
    private static function css(string $font): string
    {
        return '*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.6 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased}'
            . 'img{display:block;max-width:100%}a{color:inherit}h1,h2,h3,.brand{font-family:' . $font . ';margin:0;line-height:1.12;letter-spacing:-.01em}'
            . '.pv{position:sticky;top:0;z-index:20;display:flex;gap:12px;justify-content:center;align-items:center;flex-wrap:wrap;padding:10px 16px;background:#141414;color:#fff;font-size:14px}'
            . '.pv a{background:#e0b23c;color:#2a1d05;padding:6px 12px;border-radius:5px;text-decoration:none;font-weight:600}'
            . '.top{display:flex;justify-content:space-between;align-items:center;padding:18px clamp(16px,5vw,64px)}.brand{font-size:22px;font-weight:700;color:var(--p)}'
            . '.top nav a{margin-left:18px;text-decoration:none;font-size:14px;opacity:.75}'
            . '.btn{display:inline-block;background:var(--p);color:var(--on);padding:13px 24px;border-radius:5px;text-decoration:none;font-weight:600}'
            . '.kicker{display:inline-block;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:var(--p);margin-bottom:12px}'
            . 'section{padding:clamp(28px,6vw,72px) clamp(16px,5vw,64px)}section>h2{font-size:clamp(26px,4vw,40px);margin-bottom:22px}'
            . 'h1{font-size:clamp(34px,6vw,64px);font-weight:700}section p{color:var(--mu)}'
            . '.play{position:absolute;inset:auto 10px 10px auto;display:grid;place-items:center;width:42px;height:42px;border-radius:50%;background:rgba(0,0,0,.6);color:#fff}'
            . '.vid{display:block;position:relative}.cm,.pm,.tm,.rm,.vc,.chip,.fm{position:relative}'
            . '.ask{font-size:14px;font-weight:600;color:var(--p);text-decoration:none;border-bottom:2px solid var(--a)}'
            // heroes
            . '.h-split{display:grid;grid-template-columns:1.1fr 1fr;gap:clamp(20px,4vw,56px);align-items:center}.h-split p{font-size:18px;margin:16px 0 24px}'
            . '.h-split .art{aspect-ratio:4/5;border-radius:24px;overflow:hidden;box-shadow:0 30px 60px -30px rgba(0,0,0,.5)}.h-split .art img{width:100%;height:100%;object-fit:cover}'
            . '.h-cover{position:relative;min-height:70vh;display:flex;align-items:flex-end;padding:0;overflow:hidden}.h-cover>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}'
            . '.h-cover .shade{position:absolute;inset:0;background:linear-gradient(transparent 20%,rgba(0,0,0,.78))}.h-cover .ct{position:relative;padding:clamp(24px,6vw,72px);color:#fff}'
            . '.h-cover .ct p{color:rgba(255,255,255,.85);font-size:18px;margin:12px 0 22px}.h-cover .kicker{color:#f0c75a}'
            . '.h-feature{display:grid;grid-template-columns:1.6fr 1fr;gap:24px;align-items:center}.h-feature .fm{aspect-ratio:16/9;border-radius:14px;overflow:hidden;background:var(--card)}.h-feature .fm img{width:100%;height:100%;object-fit:cover}.h-feature p{margin:12px 0 20px}'
            . '.h-profile{padding:0 0 10px}.h-profile .cov{height:clamp(160px,30vw,320px);overflow:hidden;background:var(--card)}.h-profile .cov img{width:100%;height:100%;object-fit:cover}'
            . '.h-profile .pf{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:0 clamp(16px,5vw,64px);margin-top:12px}.h-profile .big-av{margin-top:-60px}.h-profile h1{font-size:clamp(26px,4vw,36px)}.h-profile .pf p{margin:4px 0 0}.h-profile .btn{margin-left:auto}'
            . '.big-av,.av{display:grid;place-items:center;border-radius:50%;background:var(--p);color:var(--on);font-weight:700;flex:none}.big-av{width:112px;height:112px;font-size:44px;border:5px solid var(--bg)}.av{width:40px;height:40px}'
            . '.h-vertical{position:relative;height:92vh;padding:0;overflow:hidden}.h-vertical>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}'
            . '.h-vertical .vt{position:absolute;inset:auto 0 0 0;padding:28px clamp(16px,5vw,48px) 40px;background:linear-gradient(transparent,rgba(0,0,0,.85))}.h-vertical .vt p{color:#ddd}'
            . '.h-centered,.h-editorial{text-align:center;max-width:860px;margin:0 auto}.h-centered p,.h-editorial p{font-size:19px;margin:16px auto 26px;max-width:46ch}'
            . '.h-editorial h1{font-size:clamp(46px,9vw,120px);font-weight:600;letter-spacing:-.03em}'
            . '.h-chat{max-width:680px;margin:0 auto;display:flex;flex-direction:column;gap:12px}.bub{padding:14px 18px;border-radius:18px;max-width:85%;font-size:17px}'
            . '.bub.q{align-self:flex-end;background:var(--p);color:var(--on)}.bub.a{align-self:flex-start;background:var(--card);border:1px solid var(--line)}.h-chat .btn{align-self:flex-start}'
            . '.h-glow{position:relative;text-align:center;overflow:hidden;padding-top:clamp(60px,12vw,140px);padding-bottom:clamp(60px,12vw,140px)}'
            . '.orb{position:absolute;width:520px;height:520px;border-radius:50%;filter:blur(90px);opacity:.55}.o1{background:#8ab4f8;left:-160px;top:-160px}.o2{background:#c58af9;right:-160px;bottom:-200px}'
            . '.h-glow>*:not(.orb){position:relative}.h-glow p{max-width:46ch;margin:16px auto 26px;font-size:18px}'
            . '.h-bold{display:grid;grid-template-columns:1.2fr 1fr;gap:28px;align-items:center}.h-bold h1{text-transform:uppercase;font-size:clamp(40px,8vw,96px);font-weight:800;color:var(--p)}.h-bold .bm{aspect-ratio:1;overflow:hidden;border-radius:6px}.h-bold .bm img{width:100%;height:100%;object-fit:cover}'
            . '.h-story{display:grid;grid-template-columns:1.2fr 1fr;gap:40px;align-items:center}.h-story .lede{font-size:18px;white-space:pre-line;margin:16px 0 24px}.h-story .sm{aspect-ratio:4/5;border-radius:200px 200px 18px 18px;overflow:hidden}.h-story .sm img{width:100%;height:100%;object-fit:cover}'
            // product layouts
            . '.g-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px}.card{position:relative;background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column}'
            . '.card .cm{aspect-ratio:1;overflow:hidden}.card .cm img{width:100%;height:100%;object-fit:cover}.cb{padding:16px 18px 20px;display:flex;flex-direction:column;gap:8px;flex:1}.cb h3{font-size:19px}.cb p{margin:0;font-size:15px}.cb .ask{margin-top:auto;align-self:flex-start}'
            . '.crownpick{position:absolute;z-index:2;top:10px;left:10px;background:#e0b23c;color:#2a1d05;font-size:12px;font-weight:700;padding:5px 10px;border-radius:999px}'
            . '.g-glass .card{background:rgba(255,255,255,.06);backdrop-filter:blur(14px);border-color:rgba(255,255,255,.14)}'
            . '.g-mosaic{display:grid;grid-template-columns:repeat(4,1fr);grid-auto-rows:180px;gap:10px}.g-mosaic .card:first-child{grid-column:span 2;grid-row:span 2}.g-mosaic .card{border-radius:12px}'
            . '.g-mosaic .card .cm{position:absolute;inset:0;aspect-ratio:auto}.g-mosaic .cb{position:relative;margin-top:auto;background:linear-gradient(transparent,rgba(0,0,0,.75));color:#fff}.g-mosaic .cb p{display:none}.g-mosaic .cb .ask{color:#fff}'
            . '.g-channel{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:26px 18px}.ch .cm{aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:var(--card)}.ch .cm img{width:100%;height:100%;object-fit:cover}'
            . '.ch .ct{display:flex;gap:12px;padding-top:12px}.ch h3{font-size:16px;font-family:Inter,sans-serif;font-weight:600}.ch p{margin:4px 0 0;font-size:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}'
            . '.g-posts{display:grid;gap:16px;max-width:680px;margin:0 auto}.post{background:var(--card);border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.15);overflow:hidden}'
            . '.post .ph{display:flex;align-items:center;gap:10px;padding:14px 16px 0}.post .ph small{color:var(--mu)}.post .pt{margin:10px 16px;color:var(--fg)}.pm img{width:100%;max-height:560px;object-fit:cover}'
            . '.post .pa{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--line)}'
            . '.g-thread{max-width:620px;margin:0 auto;border-top:1px solid var(--line)}.tw{display:flex;gap:12px;padding:16px 4px;border-bottom:1px solid var(--line)}.tw small{color:var(--mu)}.tw p{color:var(--fg);margin:4px 0 10px}'
            . '.tm{border-radius:16px;overflow:hidden;border:1px solid var(--line);margin-bottom:10px}.tm img{width:100%;max-height:420px;object-fit:cover}'
            . '.g-vertical{display:grid;grid-auto-flow:column;grid-auto-columns:min(360px,82vw);gap:14px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:8px}'
            . '.vc{scroll-snap-align:center;aspect-ratio:9/16;border-radius:16px;overflow:hidden;background:var(--card)}.vc img{width:100%;height:100%;object-fit:cover}'
            . '.vc .vt{position:absolute;inset:auto 0 0 0;padding:18px;background:linear-gradient(transparent,rgba(0,0,0,.85));color:#fff}.vc .vt p{color:#ddd;font-size:14px}.vc .ask{color:#fff}'
            . '.g-rows{display:grid;gap:clamp(28px,6vw,72px)}.row{display:grid;grid-template-columns:1fr 1fr;gap:clamp(20px,5vw,64px);align-items:center}.row:nth-child(even) .rm{order:2}'
            . '.rm{aspect-ratio:4/5;overflow:hidden}.rm img{width:100%;height:100%;object-fit:cover}.no{font-family:inherit;font-size:14px;letter-spacing:.2em;color:var(--p)}.rt h3{font-size:clamp(26px,4vw,40px);margin:8px 0 12px}'
            . '.g-chips{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;max-width:900px;margin:0 auto}'
            . '.chip{display:grid;grid-template-columns:96px 1fr;gap:14px;align-items:center;padding:12px;background:var(--card);border:1px solid var(--line);border-radius:16px}.chip img{width:96px;height:96px;object-fit:cover;border-radius:12px}.chip h3{font-size:16px;font-family:Inter,sans-serif}.chip p{margin:4px 0 6px;font-size:14px}'
            // the rest
            . '.about{display:grid;grid-template-columns:1fr 1.4fr;gap:40px;background:var(--card)}.about p{margin:0;font-size:17px;white-space:pre-line}'
            . '.sq-store .sub{margin:-8px 0 20px;color:var(--mu)}.sq-store .sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}'
            . '.sq-store .si{display:flex;flex-direction:column;gap:6px;text-decoration:none;color:var(--fg);background:var(--card);border:1px solid var(--line);border-radius:14px;padding:10px}'
            . '.sq-store .sp{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:var(--line)}.sq-store .sp img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}'
            . '.sq-store .si b{font-size:15px;line-height:1.3}.sq-store .pr{font-weight:800;color:var(--p)}'
            . '.sq-store .buy{margin-top:auto;align-self:flex-start;padding:6px 14px;border-radius:5px;background:var(--p);color:var(--on);font-weight:700;font-size:13px}'
            . '.sq-page{padding:0;overflow:hidden}.sq-page .pc{position:relative;height:220px;background:linear-gradient(135deg,var(--p),var(--a))}'
            . '.sq-page .pc img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}'
            . '.sq-page .pw{display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px 20px;padding:0 6vw 40px;margin-top:-48px;position:relative}'
            . '.sq-page .pa{width:104px;height:104px;border-radius:50%;overflow:hidden;border:5px solid var(--bg);background:var(--card);flex:none}.sq-page .pa img{width:100%;height:100%;object-fit:cover}'
            . '.sq-page .pw>div{flex:1;min-width:220px}.sq-page .k{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--p)}'
            . '.sq-page h2{margin:4px 0 4px}.sq-page p{margin:0;color:var(--mu)}'
            . '.hls{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:22px}.hl{border-top:3px solid var(--a);padding-top:14px}.hl h3{font-size:19px;margin-bottom:6px}.hl p{margin:0}'
            . '.ms{display:block;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--p);margin-bottom:6px}'
            . '.close{text-align:center}.close h2{margin-bottom:22px}footer{padding:28px 16px;text-align:center;font-size:13px;color:var(--mu);border-top:1px solid var(--line)}.fb{color:var(--p);font-weight:600}'
            // per template touches
            . '.t-throne .card{border-color:rgba(212,175,55,.45)}.t-throne h1,.t-throne h2{color:#f3d77a}.t-throne .brand::before{content:"\265B  "}'
            . '.t-thread .brand{color:var(--fg)}.t-thread .btn{border-radius:999px}.t-assistant .btn,.t-prism .btn{border-radius:999px}'
            . '.t-money .btn{color:#0a0a0a;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.t-money .card{border:2px solid var(--p);border-radius:6px}'
            . '.t-reign .brand{letter-spacing:.2em;text-transform:uppercase;font-size:16px}.t-reel .top{position:absolute;z-index:5;left:0;right:0;color:#fff}'
            . '@media(max-width:760px){.h-split,.h-feature,.h-bold,.h-story,.about,.row{grid-template-columns:1fr}.h-split .art,.h-bold .bm,.h-story .sm{order:-1}.row:nth-child(even) .rm{order:0}'
            . '.top nav{display:none}.g-grid{grid-template-columns:repeat(2,1fr);gap:12px}.cb{padding:12px}.cb h3{font-size:16px}.cb p{font-size:13.5px}'
            . '.g-mosaic{grid-template-columns:repeat(2,1fr);grid-auto-rows:150px}.h-profile .btn{margin-left:0}.big-av{width:88px;height:88px;font-size:34px}}';
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
