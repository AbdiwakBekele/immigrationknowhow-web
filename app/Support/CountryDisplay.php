<?php

namespace App\Support;

class CountryDisplay
{
    /**
     * Normalize assorted CSV/profile country values to a stored code when possible.
     */
    public static function normalizeForStorage(?string $value): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        $upper = strtoupper($raw);

        $aliases = [
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'U.S.A' => 'US',
            'U.S.A.' => 'US',
            'USA' => 'US',
            'CANADA' => 'CA',
            'EUROPE' => 'EU',
            'GREAT BRITAIN' => 'GB',
            'UNITED KINGDOM' => 'GB',
            'U.K.' => 'GB',
            'U.K' => 'GB',
            'UK' => 'GB',
        ];

        if (isset($aliases[$upper])) {
            return $aliases[$upper];
        }

        if (strlen($upper) === 2 && in_array($upper, PhoneDialOptions::codes(), true)) {
            return $upper;
        }

        if (array_key_exists($upper, CountryOptions::labels())) {
            return $upper;
        }

        foreach (PhoneDialOptions::selectOptions() as $option) {
            if (strtoupper($option['label']) === $upper) {
                return $option['value'];
            }
        }

        foreach (CountryOptions::labels() as $code => $label) {
            if (strtoupper($label) === $upper) {
                return $code;
            }
        }

        return $raw;
    }

    /**
     * Human-readable label for API/UI (handles ISO codes and legacy CSV strings).
     */
    public static function labelForDisplay(?string $value): ?string
    {
        $normalized = self::normalizeForStorage($value);
        if ($normalized === null) {
            return null;
        }

        $fromPhone = PhoneDialOptions::labelForCode($normalized);
        if ($fromPhone !== null && $fromPhone !== strtoupper($normalized)) {
            return $fromPhone;
        }

        $fromProduct = CountryOptions::labelForCode($normalized);
        if ($fromProduct !== null) {
            return $fromProduct;
        }

        return $normalized;
    }

    /**
     * Values that may appear in DB columns for a normalized country filter code.
     *
     * @return list<string>
     */
    public static function storageMatchValues(?string $value): array
    {
        $code = self::normalizeForStorage($value);
        if ($code === null) {
            return [];
        }

        $values = [$code, strtoupper($code)];

        $legacyByCode = [
            'US' => ['United States', 'U.S.A', 'U.S.A.', 'USA'],
            'CA' => ['Canada'],
            'EU' => ['Europe'],
            'GB' => ['Great Britain', 'U.K.', 'U.K', 'UK', 'United Kingdom'],
        ];

        if (isset($legacyByCode[$code])) {
            $values = array_merge($values, $legacyByCode[$code]);
        }

        $productLabel = CountryOptions::labelForCode($code);
        if (is_string($productLabel) && $productLabel !== '') {
            $values[] = $productLabel;
        }

        $phoneLabel = PhoneDialOptions::labelForCode($code);
        if (is_string($phoneLabel) && $phoneLabel !== '' && $phoneLabel !== strtoupper($code)) {
            $values[] = $phoneLabel;
        }

        return array_values(array_unique(array_filter($values)));
    }
}
