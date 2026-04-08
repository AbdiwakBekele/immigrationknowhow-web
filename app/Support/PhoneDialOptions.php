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
        $regions = $util->getSupportedRegions();

        $options = [];
        foreach ($regions as $code) {
            $countryCode = $util->getCountryCodeForRegion($code);
            if (! $countryCode) {
                continue;
            }

            $options[] = [
                'value' => $code,
                'label' => $labels[$code] ?? $code,
                'dial' => (string) $countryCode,
            ];
        }

        usort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
