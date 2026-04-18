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
     * Intake country/region choices (signup, onboarding, address, profile).
     * Order matches product UI. Code EU is a regional bucket, not an ISO country.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'EU' => 'Europe',
            'GB' => 'Great Britain',
        ];
    }
}
