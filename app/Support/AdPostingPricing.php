<?php

namespace App\Support;

use App\Models\Ad;

final class AdPostingPricing
{
    public static function freeLimit(): int
    {
        return max(0, (int) config('ads.free_limit', 3));
    }

    public static function standardPriceCents(): int
    {
        return max(0, (int) config('ads.default_price_cents', 2499));
    }

    public static function currency(): string
    {
        return strtoupper((string) config('ads.currency', 'USD'));
    }

    public static function adsCreatedCount(int $userId): int
    {
        return (int) Ad::query()->where('user_id', $userId)->count();
    }

    public static function freeAdsRemaining(int $userId): int
    {
        return max(0, self::freeLimit() - self::adsCreatedCount($userId));
    }

    public static function priceCentsForNewAd(int $userId): int
    {
        return self::freeAdsRemaining($userId) > 0 ? 0 : self::standardPriceCents();
    }

    /**
     * @return array{amount_cents: int, currency: string, free_limit: int, free_remaining: int, next_ad_price_cents: int, apple_product_id: string|null}
     */
    public static function apiPayload(int $userId): array
    {
        $productId = AppleIapConfig::adPublishProductId();

        return [
            'amount_cents' => self::standardPriceCents(),
            'currency' => self::currency(),
            'free_limit' => self::freeLimit(),
            'free_remaining' => self::freeAdsRemaining($userId),
            'next_ad_price_cents' => self::priceCentsForNewAd($userId),
            'apple_product_id' => $productId !== '' ? $productId : null,
        ];
    }
}
