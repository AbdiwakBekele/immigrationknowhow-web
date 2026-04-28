<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;

class PhoneDialOptions
{
    /**
     * @return list<array{value: string, label: string, dial: string}>
     */
    public static function selectOptions(): array
    {
        $util = PhoneNumberUtil::getInstance();
        $labels = CountryOptions::labels();

        $options = [];
        foreach (CountryOptions::codes() as $code) {
            $dialCode = static::fallbackDialCodeForNonIsoRegion($code);
            if ($dialCode === null) {
                $countryCode = $util->getCountryCodeForRegion($code);
                if (! $countryCode) {
                    continue;
                }
                $dialCode = (string) $countryCode;
            }

            $options[] = [
                'value' => $code,
                'label' => $labels[$code] ?? $code,
                'dial' => $dialCode,
            ];
        }

        usort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    private static function fallbackDialCodeForNonIsoRegion(string $code): ?string
    {
        return match (strtoupper($code)) {
            // Product-level grouping, not an ISO country code.
            'EU' => '00',
            default => null,
        };
    }
}
