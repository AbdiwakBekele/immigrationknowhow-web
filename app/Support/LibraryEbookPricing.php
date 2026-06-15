<?php

namespace App\Support;

use App\Models\LibraryItem;

final class LibraryEbookPricing
{
    public static function standardPriceCents(): int
    {
        return max(0, (int) config('library.standard_price_cents', 499));
    }

    public static function currency(): string
    {
        return strtoupper((string) config('library.currency', 'USD'));
    }

    public static function standardPriceAmount(): string
    {
        return number_format(self::standardPriceCents() / 100, 2, '.', '');
    }

    public static function itemPriceCents(LibraryItem $item): int
    {
        return (int) round(((float) ($item->price ?? 0)) * 100);
    }

    public static function itemQualifiesForEbookCredit(LibraryItem $item): bool
    {
        if (! $item->is_active) {
            return false;
        }

        if (! in_array($item->type, ['ebook', 'audiobook'], true)) {
            return false;
        }

        $requiresPaid = (bool) $item->is_premium || self::itemPriceCents($item) > 0;
        if (! $requiresPaid) {
            return false;
        }

        return self::itemPriceCents($item) === self::standardPriceCents();
    }
}
