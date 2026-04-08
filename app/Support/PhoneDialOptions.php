<?php

namespace App\Support;

class PhoneDialOptions
{
    /**
     * @return list<array{value: string, label: string, dial: string}>
     */
    public static function selectOptions(): array
    {
        /** @var array<string, string> $dialByCountry ISO 3166-1 alpha-2 => calling code (digits only) */
        $dialByCountry = [
            'US' => '1',
            'CA' => '1',
            'GB' => '44',
            'AU' => '61',
            'DE' => '49',
            'FR' => '33',
            'IT' => '39',
            'ES' => '34',
            'NL' => '31',
            'SE' => '46',
            'NO' => '47',
            'IN' => '91',
            'CN' => '86',
            'JP' => '81',
            'KR' => '82',
            'PH' => '63',
            'MX' => '52',
            'BR' => '55',
            'NG' => '234',
            'ET' => '251',
            'KE' => '254',
            'GH' => '233',
            'ZA' => '27',
            'IL' => '972',
            'TR' => '90',
            'PL' => '48',
            'UA' => '380',
            'RU' => '7',
            'EG' => '20',
            'SA' => '966',
            'AE' => '971',
        ];

        $options = [];
        foreach (CountryOptions::labels() as $code => $label) {
            if ($code === 'OTHER' || ! isset($dialByCountry[$code])) {
                continue;
            }
            $options[] = [
                'value' => $code,
                'label' => $label,
                'dial' => $dialByCountry[$code],
            ];
        }

        usort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
