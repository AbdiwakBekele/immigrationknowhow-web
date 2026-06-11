<?php

namespace App\Support;

/**
 * Display pricing for the AI Assistant subscription (mobile + web).
 * Matches the default provider monthly plan ($9.99) — no separate env var.
 */
final class AiAssistantPricing
{
    private const MONTHLY_PRICE_CENTS = 999;

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