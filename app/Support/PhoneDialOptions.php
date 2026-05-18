<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberUtil;
use Locale;

class PhoneDialOptions
{
    /**
     * Bump if the option shape or build logic changes (invalidates app cache).
     */
    private const CACHE_VERSION = 'v1';

    /**
     * All regions libphonenumber knows, with ITU calling codes (for phone verification pickers).
     *
     * Built once and cached: iterating every region loads hundreds of metadata files and can exceed
     * PHP max_execution_time on slow disks (e.g. Windows + Herd) without caching.
     *
     * @return list<array{value: string, label: string, dial: string}>
     */
    public static function selectOptions(): array
    {
        return Cache::remember(
            'app.support.phone_dial_options.'.self::CACHE_VERSION,
            now()->addDays(30),
            static fn (): array => static::buildSelectOptionsUncached(),
        );
    }

    /**
     * @return list<array{value: string, label: string, dial: string}>
     */
    private static function buildSelectOptionsUncached(): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

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
