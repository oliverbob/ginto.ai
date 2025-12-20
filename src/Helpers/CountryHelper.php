<?php

namespace Ginto\Helpers;

class CountryHelper
{
    public static function getCountries(): array
    {
        $file = __DIR__ . '/../Data/countries.json';
        $data = json_decode(file_get_contents($file), true);

        // Minimal country dial code list
        // (Add more later — this is just enough to run now)
        $dialCodes = [
            'PH' => '+63',
            'US' => '+1',
            'CA' => '+1',
            'GB' => '+44',
            'AU' => '+61',
            'IN' => '+91',
            'SG' => '+65',
            'JP' => '+81',
        ];

        $countries = [];
        foreach ($data as $item) {
            $alpha2 = $item['alpha-2'];
            $countries[$alpha2] = [
                'name' => $item['name'],
                'dial_code' => $dialCodes[$alpha2] ?? null
            ];
        }

        return $countries;
    }
}
