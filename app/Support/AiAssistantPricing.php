<?php

namespace App\Support;

/**
 * Display and Stripe fallback pricing for the AI Assistant subscription (mobile + web).
 * Matches App Store Connect ($4.99/month) — no separate env var.
 */
final class AiAssistantPricing
{
    private const MONTHLY_PRICE_CENTS = 499;

    private const CURRENCY = 'USD';

    public static function monthlyPriceCents(): int
    {
        return self::MONTHLY_PRICE_CENTS;
    }

    public static function currency(): string
    {
        return self::CURRENCY;
    }

    public static function monthlyPriceAmount(): string
    {
        return number_format(self::MONTHLY_PRICE_CENTS / 100, 2, '.', '');
    }
}