<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * The shop description and the photos' facts in, a site plan out.
 *
 * The builder model sees TEXT only (the vision answers as JSON, never the
 * pictures) and answers with a plan, not HTML: Renderer turns the plan into
 * the page, escaping every word. So a model can choose colours, words and
 * order, and can never put a script on a seller's site.
 */
final class Builder
{
    /** Kept byte-identical between calls: a repeated prefix is billed at half. */
    private const SYSTEM = 'You design one-page online shops for Filipino sellers on SilverQueen. You get the seller\'s own '
        . 'description of the shop and, for each product photo, what was seen in it. Answer ONLY with JSON in this shape: '
        . '{"brand":{"name":string,"tagline":string},"theme":{"primary":"#hex","accent":"#hex","background":"#hex","text":"#hex",'
        . '"font":"serif"|"sans"|"display","mood":"light"|"dark"},"hero":{"headline":string,"subheadline":string,"cta":string},'
        . '"about":{"title":string,"body":string},"products":[{"photo":number,"name":string,"description":string}],'
        . '"highlights":[{"title":string,"body":string}],"closing":{"headline":string,"cta":string}}. Rules: build the colours '
        . 'from the photos\' colours so the site matches the products, with readable contrast between text and background; '
        . 'one product per photo, "photo" is its 0-based index; never invent prices, stock, sizes, discounts, delivery times, '
        . 'awards or reviews; keep the seller\'s own facts; write in the language the seller wrote in; headline at most 8 '
        . 'words; product descriptions at most 35 words; 3 highlights at most; warm, simple, honest. Never copy a caption '
        . 'word for word into the tagline, headline or names, and never use hashtags or @mentions: write fresh, short words. '
        . 'A photo whose main subject is a person, a crowd or an event is not a product: name it for the service or work it '
        . 'shows (or "Our team", "Our events"), never "laptop on table" or what is merely visible.';

    public function __construct(private Ai $groq)
    {
    }

    /**
     * @param list<array{status:string,vision:?array,palette:array}> $photos in upload order
     * @return array<string,mixed> a clean plan (Renderer::clean has been applied)
     */
    public function build(string $description, array $photos, array $ctx): array
    {
        $facts = [];
        foreach ($photos as $i => $p) {
            $facts[] = ['photo' => $i] + ($p['vision'] ?? ['needs_manual_input' => true]) + ['photo_colors' => array_slice($p['palette'], 0, 4)];
        }
        $user = "Seller's description:\n" . trim($description) . "\n\nPhotos:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $plan = null;
        for ($attempt = 0; $attempt < 2 && $plan === null; $attempt++) {
            $r = $this->groq->chat('site_generation', [['role' => 'system', 'content' => self::SYSTEM], ['role' => 'user', 'content' => $user]], $ctx + ['json' => true]);
            $plan = Renderer::clean(json_decode($r['content'], true), count($photos));
        }
        if ($plan === null) {
            // The model did not give a usable plan twice: the seller still gets a site, built from their own words.
            $plan = Renderer::fallback($description, $photos);
        }

        return $plan;
    }
}
