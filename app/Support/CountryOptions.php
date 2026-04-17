<?php

namespace App\Support;

class CountryOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $code) => ['value' => $code, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::labels());
    }

    public static function labelForCode(?string $code): ?string
    {
        $code = is_string($code) ? trim($code) : '';
        if ($code === '') {
            return null;
        }

        return self::labels()[$code] ?? $code;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'IN' => 'India',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'PH' => 'Philippines',
            'MX' => 'Mexico',
            'BR' => 'Brazil',
            'NG' => 'Nigeria',
            'ET' => 'Ethiopia',
            'KE' => 'Kenya',
            'GH' => 'Ghana',
            'ZA' => 'South Africa',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'PL' => 'Poland',
            'UA' => 'Ukraine',
            'RU' => 'Russia',
            'EG' => 'Egypt',
            'SA' => 'Saudi Arabia',
            'AE' => 'United Arab Emirates',
            'OTHER' => 'Other / not listed',
        ];
    }
}
