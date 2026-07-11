<?php
namespace Ginto\Services\Strategies;

/**
 * Curated "system prompt" templates the operator can inject from the /gtb dashboard.
 * The chosen one (GTB_ACTIVE_PROMPT) OVERRIDES the operator instructions set on
 * /gtb-settings (GTB_CUSTOM_INSTRUCTIONS); clearing it reverts to that default.
 *
 * Each is written for the data the bot actually has (24h change, price, 24h high/low,
 * 24h volume) and reaffirms the hard rule: every entry is protected by an OCO / stop.
 */
final class GtbPrompts
{
    /**
     * The built-in, win-focused strategy used when neither a dashboard prompt nor a
     * /gtb-settings operator prompt is set — so the bot is never left ungoverned.
     */
    public const DEFAULT_TEXT = 'Trade to win with disciplined momentum. Focus on liquid USDT pairs with real 24h volume. Prefer clean, orderly moves with volume behind them: strong gainers still in an uptrend, fresh breakouts of the 24h high, and healthy pullbacks that hold support. Avoid the traps: skip anything already parabolic or up more than 40 percent in 24h, thin or illiquid names, and choppy structure. Only take high-conviction setups; when a trade is not clearly favorable, SKIP. Every entry is protected by a resting OCO or stop-loss on the exchange, sized within the capital rules. Cut losers quickly at the stop and let winners run by trailing the stop once the trade is working. Fewer good trades beat many marginal ones.';

    public static function defaultText(): string { return self::DEFAULT_TEXT; }

    /** @var array<string,array{name:string,desc:string,text:string}> */
    public const ALL = [
        'gainers' => [
            'name' => 'Top-3 Gainer Hunter',
            'desc' => 'Chase the top 3 gainers pressing their highs; profit-locking OCO locks ≥1.5%, aims 5–8%. Many wins, 15–45m holds.',
            'text' => 'Hunt the day\'s top 3 gainers and bank many small, protected wins. Focus on the three strongest liquid USDT movers (prioritise the number one gainer) that are STILL PRESSING their 24h high on real volume — never a coin that already spiked and collapsed back off its high, and never anything thin or choppy. Prefer to buy a slight dip off the high; but if a mover keeps running away without dipping and you have already missed similar runners this session, CHASE it and bid the strength — always protected. EVERY entry is an OCO. The target is a minimum of 1.5 percent and ideally 5 to 8 percent. Once the trade is up about 2 percent, the stop ratchets ONTO THE PLUS SIDE: it locks in at least 1.5 percent profit and then trails upward under the peak, so a winner can never turn into a loss — because top gainers drop fast, your downside stop becomes a profit-taking stop. Hold at least 15 minutes to let it work, but no more than 30 to 60 minutes; if it stalls, rotate to the next mover. Do not marry a position, do not widen a stop downward, do not chase a blow-off top already up more than 60 percent. Many locked wins beat one round-trip you gave back. Speed and protection are the edge: the only losing trade is one you were too slow to OCO.',
        ],
        'runner' => [
            'name' => 'Fastest-Mover Chaser',
            'desc' => 'Chase the fastest-accelerating gainer on volume; wide trail for 2x-5x runners. Always OCO.',
            'text' => 'Hunt the fastest-accelerating momentum right now. Among liquid USDT movers with 24h volume of at least 5 million, favor the coin whose price is rising fastest relative to its recent base with clean breakout structure. Chase strength but never the blow-off top: skip anything already up more than 40 percent in 24h or stretched far above its 24h high. Enter only with real volume behind the move. ALWAYS protect with an OCO: a hard stop-loss just under the breakout level plus a take-profit target. Once the trade is up 15 percent or more, trail the stop wide so a genuine 2x to 5x runner is not cut short by a normal pullback. Cut losers fast at the stop. Prefer fewer high-conviction entries over many weak ones.',
        ],
        'breakout' => [
            'name' => 'Breakout Sniper',
            'desc' => 'Clean 24h-high breakouts on rising volume; tight stop under the level, measured target.',
            'text' => 'Trade clean breakouts only. Enter a coin pressing or just reclaiming its 24h high on clearly rising volume, with tight well-defined risk: stop-loss just under the breakout level and a take-profit at a measured move above. Skip extended coins far from their base or up more than 30 percent in 24h. Favor liquid pairs with 24h volume of at least 5 million so slippage stays small. Every entry uses an OCO with both a stop and a target. Bank the win at target and do not widen the stop hoping for more. Quality over quantity: one clean breakout beats five marginal ones.',
        ],
        'pullback' => [
            'name' => 'Pullback Accumulator',
            'desc' => 'Buy the dip in a strong uptrend; tight stop, bounce target. High win rate.',
            'text' => 'Buy strength on a dip, not a collapse. Target coins that are net up on the day and in a clear uptrend but have pulled back a few percent off their 24h high into the upper half of the day range, a healthy retracement with buyers still in control. Enter near support with a tight stop just below the pullback low and a bounce target back toward the high. Skip anything breaking down through its range or making lower lows. Liquid pairs only. Always an OCO with stop and target. This is a high win rate pullback-in-trend play: take the bounce and move on.',
        ],
        'scalp' => [
            'name' => 'Scalp Grinder',
            'desc' => 'Fast small momentum scalps; tight 1.5% stop / 2.5% target. Many small wins.',
            'text' => 'Grind small, fast, high-probability momentum scalps. Enter the strongest short-term mover with a clean orderly uptrend and real volume, holding minutes not hours. Tight stop near 1.5 percent and a quick take-profit near 2.5 percent, always as an OCO. Skip choppy, thin, or parabolic names. Aim for a high win rate with small consistent gains and strict discipline: no averaging down, no widening stops, exit the moment momentum stalls. Many small wins compound; one blown stop erases them, so protection is non-negotiable.',
        ],
        'trend' => [
            'name' => 'Trend Rider',
            'desc' => 'Enter confirmed trends, trail wide, let winners run for big multiples.',
            'text' => 'Ride sustained trends for outsized gains. Enter confirmed uptrends with momentum and volume behind them. Keep the initial stop tight to cap risk, but once the trade works, trail the stop wide and let it run, aiming for large multiples rather than quick scalps. No fixed take-profit ceiling: the trailing stop books the gain. Cut losers immediately at the initial stop. Always protect with a resting stop, using an OCO where a target applies. Fewer bolder positions in the best trends beat many small trades. Patience on winners, ruthlessness on losers.',
        ],
        'rapid' => [
            'name' => 'Rapid Scalp (1-3h)',
            'desc' => 'High-turnover quick scalps for a short session; tight stop, fast small target, high hit-rate. Not guaranteed.',
            'text' => 'Trade actively within a short 1-3 hour window, aiming for many small, quick wins rather than a few big ones. Take ONLY the cleanest fast momentum movers with real volume and orderly structure; never chase parabolic or choppy names. Target a fast take-profit around 1.5-2 percent with a tight stop around 1-1.5 percent, always as an OCO so risk is capped on every trade. Exit the instant momentum stalls and rotate to the next mover; do not marry a position. Discipline over frequency: a string of small losses means slow down and be more selective, not size up. Protecting capital comes before any trade. No trade is guaranteed, so keep each loss tiny.',
        ],
    ];

    /** Preset metadata for the dashboard picker (without the full text). */
    public static function cards(): array
    {
        $out = [];
        foreach (self::ALL as $k => $p) $out[] = ['key' => $k, 'name' => $p['name'], 'desc' => $p['desc']];
        return $out;
    }

    public static function text(string $key): ?string
    {
        return self::ALL[$key]['text'] ?? null;
    }

    /** Which preset (if any) a stored prompt string matches. */
    public static function matchKey(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') return null;
        foreach (self::ALL as $k => $p) {
            if (trim($p['text']) === $text) return $k;
        }
        return null;
    }
}
