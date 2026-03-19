<?php
/**
 * ShippingCalculator — Shopee/Lazada-style shipping fee estimation.
 *
 * Chargeable weight = max(actual weight, volumetric weight)
 * Volumetric weight (kg) = (L cm × W cm × H cm) / divisor
 *
 * Defaults are deliberately conservative (use a smaller divisor = higher fee)
 * so the seller is never surprised by a shortfall when a real logistics partner
 * is later plugged in.  Actual measurement by the courier is always the source
 * of truth; any shortfall is deducted from the seller payout per platform rules.
 *
 * Usage
 *   $calc = new ShippingCalculator();
 *   $fee  = $calc->estimate($items, $buyerZone);
 */

namespace Ginto\Services;

class ShippingCalculator
{
    // ------------------------------------------------------------------
    // Volumetric divisors — lower = heavier = more conservative estimate
    // ------------------------------------------------------------------

    /** Default safe divisor (used when no logistics partner is configured) */
    public const DIVISOR_SAFE     = 3500;  // conservative — protects seller

    /** Standard domestic courier divisor (e.g. J&T, Ninja Van PH) */
    public const DIVISOR_STANDARD = 5000;

    // ------------------------------------------------------------------
    // Domestic shipping zones (Philippines example; extend as needed)
    // ------------------------------------------------------------------

    /** Zone identifiers */
    public const ZONE_INTRA_CITY   = 'intra_city';    // same city / metro
    public const ZONE_METRO_LUZON  = 'metro_luzon';   // Metro ↔ Luzon provs
    public const ZONE_VISAYAS      = 'visayas';
    public const ZONE_MINDANAO     = 'mindanao';
    public const ZONE_ISLAND       = 'island_province'; // remote / island
    public const ZONE_INTERNATIONAL = 'international';

    /**
     * Base rate table: zone → [base_fee_php, per_kg_php, free_kg_included]
     *
     * Rates are intentionally padded slightly above the cheapest courier to give
     * a safe estimate.  The seller absorbs any discrepancy between ESF and ASF.
     */
    private const ZONE_RATES = [
        self::ZONE_INTRA_CITY    => ['base' => 50.00,  'per_kg' => 10.00, 'free_kg' => 1],
        self::ZONE_METRO_LUZON   => ['base' => 80.00,  'per_kg' => 15.00, 'free_kg' => 1],
        self::ZONE_VISAYAS       => ['base' => 110.00, 'per_kg' => 18.00, 'free_kg' => 1],
        self::ZONE_MINDANAO      => ['base' => 110.00, 'per_kg' => 18.00, 'free_kg' => 1],
        self::ZONE_ISLAND        => ['base' => 180.00, 'per_kg' => 25.00, 'free_kg' => 1],
        self::ZONE_INTERNATIONAL => ['base' => 600.00, 'per_kg' => 80.00, 'free_kg' => 0],
    ];

    /** Fallback zone when nothing is specified */
    private const DEFAULT_ZONE = self::ZONE_METRO_LUZON;

    /**
     * Minimum chargeable weight enforced per parcel (kg).
     * Most PH couriers enforce a 0.5 kg or 1 kg minimum.
     */
    private const MIN_CHARGEABLE_KG = 0.5;

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Estimate shipping fee for a list of items going to a single buyer zone.
     *
     * Each $item should contain:
     *   weight_kg  float   Physical weight of item + packaging (kg)
     *   length_cm  float   Longest side (cm)
     *   width_cm   float   Second side (cm)
     *   height_cm  float   Shortest side / height (cm)
     *   quantity   int     Number of units
     *
     * Missing dimension fields are treated as 0 (dimension fee contribution
     * falls back to actual weight only — still produces a valid estimate).
     *
     * @param  array<array<string,mixed>> $items     Product rows from DB
     * @param  string                     $zone      One of the ZONE_* constants
     * @param  int                        $divisor   Volumetric divisor override
     * @return array{
     *   zone: string,
     *   chargeable_weight_kg: float,
     *   actual_weight_kg: float,
     *   volumetric_weight_kg: float,
     *   estimated_fee: float,
     *   breakdown: array,
     *   dimensions_complete: bool,
     *   has_logistics_partner: bool
     * }
     */
    public function estimate(
        array  $items,
        string $zone    = self::DEFAULT_ZONE,
        int    $divisor = self::DIVISOR_SAFE,
        bool   $hasLogisticsPartner = false
    ): array {
        $zone = $this->normalizeZone($zone);

        // -- Step 1: combine items -------------------------------------------
        [$totalActual, $totalVol, $dimensionsComplete] = $this->combineItems($items, $divisor);

        // -- Step 2: chargeable weight -----------------------------------------
        $chargeableKg = max($totalActual, $totalVol, self::MIN_CHARGEABLE_KG);

        // -- Step 3: fee from rate table ---------------------------------------
        $fee = $this->feeFromRate($zone, $chargeableKg);

        return [
            'zone'                  => $zone,
            'chargeable_weight_kg'  => round($chargeableKg, 3),
            'actual_weight_kg'      => round($totalActual, 3),
            'volumetric_weight_kg'  => round($totalVol, 3),
            'estimated_fee'         => round($fee, 2),
            'dimensions_complete'   => $dimensionsComplete,
            'has_logistics_partner' => $hasLogisticsPartner,
            'divisor_used'          => $divisor,
            'note'                  => $this->estimateNote($dimensionsComplete, $hasLogisticsPartner),
        ];
    }

    /**
     * Compute the volumetric weight of a single parcel.
     *
     * @param  float $lengthCm
     * @param  float $widthCm
     * @param  float $heightCm
     * @param  int   $divisor
     * @return float  Volumetric weight in kg
     */
    public static function volumetricWeight(float $lengthCm, float $widthCm, float $heightCm, int $divisor = self::DIVISOR_SAFE): float
    {
        if ($lengthCm <= 0 || $widthCm <= 0 || $heightCm <= 0 || $divisor <= 0) {
            return 0.0;
        }
        return ($lengthCm * $widthCm * $heightCm) / $divisor;
    }

    /**
     * Return the chargeable weight for a single item.
     *
     * @param  float $actualKg
     * @param  float $lengthCm
     * @param  float $widthCm
     * @param  float $heightCm
     * @param  int   $divisor
     * @return float
     */
    public static function chargeableWeight(
        float $actualKg,
        float $lengthCm  = 0.0,
        float $widthCm   = 0.0,
        float $heightCm  = 0.0,
        int   $divisor   = self::DIVISOR_SAFE
    ): float {
        $vol = self::volumetricWeight($lengthCm, $widthCm, $heightCm, $divisor);
        return max($actualKg, $vol, self::MIN_CHARGEABLE_KG);
    }

    /**
     * Infer a shipping zone from a buyer's province/city string.
     * A proper mapping table should be used in production; this is a safe
     * fallback that matches obvious Metro Manila keywords.
     */
    public static function inferZone(string $province = '', string $city = ''): string
    {
        $haystack = strtolower($province . ' ' . $city);

        $intraCity = ['quezon city', 'manila', 'makati', 'taguig', 'pasig', 'mandaluyong',
                      'marikina', 'parañaque', 'paranaque', 'las piñas', 'las pinas',
                      'caloocan', 'valenzuela', 'malabon', 'navotas', 'pasay', 'muntinlupa',
                      'san juan', 'pateros'];
        foreach ($intraCity as $kw) {
            if (str_contains($haystack, $kw)) return self::ZONE_INTRA_CITY;
        }

        $metroLuzon = ['bulacan', 'cavite', 'laguna', 'batangas', 'rizal', 'pampanga',
                       'nueva ecija', 'tarlac', 'pangasinan', 'bataan', 'zambales',
                       'metro manila', 'ncr', 'national capital'];
        foreach ($metroLuzon as $kw) {
            if (str_contains($haystack, $kw)) return self::ZONE_METRO_LUZON;
        }

        $visayas = ['cebu', 'iloilo', 'bacolod', 'tacloban', 'dumaguete', 'bohol',
                    'leyte', 'samar', 'negros', 'panay', 'palawan', 'aklan',
                    'antique', 'capiz', 'guimaras', 'biliran', 'eastern samar',
                    'northern samar', 'western samar', 'siquijor', 'southern leyte'];
        foreach ($visayas as $kw) {
            if (str_contains($haystack, $kw)) return self::ZONE_VISAYAS;
        }

        $mindanao = ['davao', 'cagayan de oro', 'zamboanga', 'general santos', 'gensan',
                     'cotabato', 'bukidnon', 'misamis', 'lanao', 'maguindanao', 'basilan',
                     'sulu', 'tawi-tawi', 'agusan', 'surigao', 'north cotabato',
                     'south cotabato', 'sarangani', 'sultan kudarat', 'compostela'];
        foreach ($mindanao as $kw) {
            if (str_contains($haystack, $kw)) return self::ZONE_MINDANAO;
        }

        // Default to a mid-tier zone rather than intra-city to avoid undercharging
        return self::DEFAULT_ZONE;
    }

    /**
     * Return safe rate table for display / documentation.
     */
    public static function rateTable(): array
    {
        return self::ZONE_RATES;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Combine multiple items (with quantities) into totals.
     * Multi-item strategy mirrors Shopee/Lazada:
     *  - Sum actual weights
     *  - Outer box = max length, max width, sum of heights
     *  - Then recompute volumetric for combined box
     *
     * @return array{0:float,1:float,2:bool}  [actualKg, volKg, dimensionsComplete]
     */
    private function combineItems(array $items, int $divisor): array
    {
        $totalActual    = 0.0;
        $maxLength      = 0.0;
        $maxWidth       = 0.0;
        $sumHeight      = 0.0;
        $hasDimensions  = false;
        $allHaveDim     = true;

        foreach ($items as $item) {
            $qty = max(1, (int)($item['quantity'] ?? $item['qty'] ?? 1));

            $weightKg  = max(0.0, (float)($item['weight_kg'] ?? 0));
            $lengthCm  = max(0.0, (float)($item['length_cm'] ?? 0));
            $widthCm   = max(0.0, (float)($item['width_cm']  ?? 0));
            $heightCm  = max(0.0, (float)($item['height_cm'] ?? 0));

            $totalActual += $weightKg * $qty;

            $hasDims = ($lengthCm > 0 && $widthCm > 0 && $heightCm > 0);
            if ($hasDims) {
                $hasDimensions = true;
                $maxLength = max($maxLength, $lengthCm);
                $maxWidth  = max($maxWidth, $widthCm);
                $sumHeight += $heightCm * $qty;
            } else {
                $allHaveDim = false;
            }
        }

        $volKg = 0.0;
        if ($hasDimensions) {
            $volKg = self::volumetricWeight($maxLength, $maxWidth, $sumHeight, $divisor);
        }

        $dimensionsComplete = $hasDimensions && $allHaveDim && count($items) > 0;

        return [$totalActual, $volKg, $dimensionsComplete];
    }

    private function feeFromRate(string $zone, float $chargeableKg): float
    {
        $rates   = self::ZONE_RATES[$zone] ?? self::ZONE_RATES[self::DEFAULT_ZONE];
        $base    = (float)$rates['base'];
        $perKg   = (float)$rates['per_kg'];
        $freeKg  = (int)($rates['free_kg'] ?? 0);

        $billableKg = max(0.0, $chargeableKg - $freeKg);
        return $base + ($billableKg * $perKg);
    }

    private function normalizeZone(string $zone): string
    {
        $valid = [
            self::ZONE_INTRA_CITY,
            self::ZONE_METRO_LUZON,
            self::ZONE_VISAYAS,
            self::ZONE_MINDANAO,
            self::ZONE_ISLAND,
            self::ZONE_INTERNATIONAL,
        ];
        return in_array($zone, $valid, true) ? $zone : self::DEFAULT_ZONE;
    }

    private function estimateNote(bool $dimensionsComplete, bool $hasLogisticsPartner): string
    {
        if ($hasLogisticsPartner) {
            return 'Estimated by logistics partner. Actual measurement on pickup may differ.';
        }
        if (!$dimensionsComplete) {
            return 'Some items are missing weight or dimensions. Fee uses actual weight only; complete your listings to get an accurate estimate.';
        }
        return 'Estimated fee. Actual courier measurement on pickup is the final basis. Discrepancy will be deducted from seller payout.';
    }
}
