<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;
use Locale;

class PhoneDialOptions
{
    /**
     * All regions libphonenumber knows, with ITU calling codes (for phone verification pickers).
     *
     * @return list<array{value: string, label: string, dial: string}>
     */
    public static function selectOptions(): array
    {
        $util = PhoneNumberUtil::getInstance();
        $options = [];

        foreach ($util->getSupportedRegions() as $region) {
            $region = (string) $region;
            $countryCode = $util->getCountryCodeForRegion($region);
            if ($countryCode <= 0) {
                continue;
            }

            $options[] = [
                'value' => $region,
                'label' => static::regionLabel($region),
                'dial' => (string) $countryCode,
            ];
        }

        usort($options, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    private static function regionLabel(string $regionCode): string
    {
        if (class_exists(Locale::class)) {
            $name = Locale::getDisplayRegion('und_'.$regionCode, 'en');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $regionCode;
    }
}
