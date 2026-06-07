<?php

namespace App\Support\Library;

class LibraryMatchNormalizer
{
    public const CANONICAL_CATEGORIES = [
        'IMMIGRATION - RELOCATING INTO A DIFFERENT COUNTRY',
        'TOURISM - VISITING OTHER COUNTRIES FOR LEISURE & ADVENTURE',
        'MENTAL HEALTH AWARENESS',
        'MEDICAL HEALTH & WELLNESS',
        'FAMILY, PARENTING AND RELATIONSHIP',
        'CHILD DEVELOPMENT AND WELLNESS',
        'PROFESSIONAL DEVELOPMENT',
        'OTHERS',
    ];

    /**
     * Normalize a book title for fuzzy matching against database records.
     */
    public static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $title = mb_strtolower($title, 'UTF-8');
        $title = static::replaceUnicodeDashes($title);
        $title = static::replaceApostropheVariants($title);

        $title = preg_replace('/\s*&\s*/u', ' and ', $title) ?? $title;
        $title = preg_replace('/\+/u', '', $title) ?? $title;
        $title = preg_replace('/[:\.,;!?\'"`´()\[\]{}«»]/u', ' ', $title) ?? $title;
        $title = preg_replace('/[\-–—−]+/u', ' ', $title) ?? $title;
        $title = preg_replace('/\s+s\s+/u', 's ', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return trim($title);
    }

    /**
     * Secondary title key that ignores spaces (post-partum vs postpartum, co-occurring vs cooccurring).
     */
    public static function compactTitleKey(string $title): string
    {
        $normalized = static::normalizeTitle($title);

        return str_replace(' ', '', $normalized);
    }

    /**
     * Normalize category names so en-dash and hyphen variants match.
     */
    public static function normalizeCategoryKey(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = mb_strtoupper($name, 'UTF-8');
        $name = static::replaceUnicodeDashes($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Return the canonical hyphenated category name when recognized.
     */
    public static function canonicalCategoryName(string $name): string
    {
        $key = static::normalizeCategoryKey($name);

        foreach (static::CANONICAL_CATEGORIES as $canonical) {
            if (static::normalizeCategoryKey($canonical) === $key) {
                return $canonical;
            }
        }

        return static::replaceUnicodeDashes(trim($name));
    }

    /**
     * Map a region label to library_items.regions storage format.
     *
     * @return array{all: bool, regions: ?array<int, string>}
     */
    public static function parseRegionWritten(string $regionWritten): array
    {
        $value = trim($regionWritten);
        if ($value === '') {
            return ['all' => false, 'regions' => ['usa']];
        }

        $normalized = mb_strtoupper(static::replaceUnicodeDashes($value), 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if (in_array($normalized, ['ALL', 'OTHERS'], true)) {
            return ['all' => true, 'regions' => null];
        }

        $parts = array_map('trim', preg_split('/\s*-\s*/u', $normalized) ?: []);
        $mapped = [];

        foreach ($parts as $part) {
            $region = static::mapRegionToken($part);
            if ($region !== null) {
                $mapped[] = $region;
            }
        }

        $mapped = array_values(array_unique($mapped));

        if ($mapped === []) {
            return ['all' => false, 'regions' => ['usa']];
        }

        if (count($mapped) === count(\App\Models\LibraryItem::supportedRegions())) {
            return ['all' => true, 'regions' => null];
        }

        return ['all' => false, 'regions' => $mapped];
    }

    public static function mapRegionToken(string $token): ?string
    {
        $token = mb_strtoupper(trim(static::replaceUnicodeDashes($token)), 'UTF-8');
        $token = preg_replace('/\s+/u', ' ', $token) ?? $token;

        return match ($token) {
            'USA', 'US', 'U.S.', 'U.S.A.', 'NORTH AMERICA', 'NORTH AMERICA USA' => 'usa',
            'CANADA', 'NORTH AMERICA CANADA' => 'canada',
            'GREAT BRITAIN', 'UK', 'U.K.', 'UNITED KINGDOM', 'BRITAIN' => 'great_britain',
            'EUROPE', 'EU' => 'europe',
            default => null,
        };
    }

    public static function regionsAreEqual(?array $left, ?array $right): bool
    {
        $left = $left === null ? null : array_values(array_unique($left));
        $right = $right === null ? null : array_values(array_unique($right));

        if ($left === null && $right === null) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        sort($left);
        sort($right);

        return $left === $right;
    }

    private static function replaceUnicodeDashes(string $value): string
    {
        return str_replace(['–', '—', '−'], '-', $value);
    }

    private static function replaceApostropheVariants(string $value): string
    {
        return str_replace(["\u{2019}", "\u{2018}", "\u{2032}", '`', '´'], '', $value);
    }
}
