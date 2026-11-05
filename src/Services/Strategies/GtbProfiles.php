<?php
namespace Ginto\Services\Strategies;

use Ginto\Support\Env;

/**
 * Trading profiles — two "bots" with different temperaments that share the SAME
 * capital pool. Aggressive chases strong momentum with wider targets; Conservative
 * protects capital first and only takes high-conviction setups. Which ones are
 * active is set on /gtb-settings (GTB_PROFILES). With one capital slot they take
 * turns; as profit unlocks more slots they run side by side.
 *
 * A profile biases three things: preferred templates (tactics), a risk multiplier
 * on the template's stop/target, and the posture handed to the AI on every decision.
 */
final class GtbProfiles
{
    /** @var array<string,array{name:string,templates:string[],slMult:float,tpMult:float,posture:string}> */
    public const ALL = [
        'conservative' => [
            'name'      => 'Conservative',
            'templates' => ['trend', 'pullback'],
            'slMult'    => 0.85,   // tighter stop — cut losers fast
            'tpMult'    => 0.85,   // bank profit quickly
            'posture'   => 'Trade like a disciplined, capital-preservation-first trader. Only take high-conviction '
                         . 'setups with a clean risk/reward. Skip anything parabolic, thin, choppy, or already extended — '
                         . 'when in doubt, SKIP. A missed trade costs nothing; a bad one costs capital.',
        ],
        'aggressive' => [
            'name'      => 'Aggressive',
            // Hunt the top gainers FIRST with a profit-locking OCO (locks ≥1.5%, aims 5–8%), then
            // ride cleaner trends; breakout/scalp are the fallback. This chases the strongest movers
            // while the ratcheting OCO banks the plus side — top gainers drop fast.
            'templates' => ['gainers', 'trend', 'breakout', 'scalp'],
            'slMult'    => 1.20,   // more room to breathe (also widens the trailing distance)
            'tpMult'    => 1.50,   // let winners run further
            'posture'   => 'Trade like a decisive momentum trader hunting the fastest movers. On a strong, real '
                         . 'uptrend prefer to RIDE it with the trailing stop rather than take a quick scalp — let the '
                         . 'winner run and let the ratcheting stop decide the exit. Tolerate normal pullbacks; you may '
                         . 'take a setup that is slightly extended if thrust and volume are convincing. Still respect '
                         . 'the hard stop — aggressive, not reckless.',
        ],
    ];

    /** Enabled profile keys, in a stable order. Defaults to both. */
    public static function enabled(): array
    {
        $raw = (string) (Env::get('GTB_PROFILES', 'conservative,aggressive') ?? '');
        $keys = array_filter(array_map('trim', explode(',', $raw)));
        $keys = array_values(array_filter($keys, static fn($k) => isset(self::ALL[$k])));
        return $keys ?: ['conservative'];
    }

    /** Config for a profile key (falls back to conservative). */
    public static function config(string $key): array
    {
        return self::ALL[$key] ?? self::ALL['conservative'];
    }
}
