<?php

namespace App\Support;

final class StripeConfig
{
    public static function hasSecretKey(): bool
    {
        $secret = config('services.stripe.secret');

        return is_string($secret) && self::looksLikeSecretKey($secret);
    }

    public static function hasPublishableKey(): bool
    {
        $publishable = config('services.stripe.key');

        return is_string($publishable) && self::looksLikePublishableKey($publishable);
    }

    public static function checkoutConfigured(): bool
    {
        return self::hasSecretKey() && self::hasPublishableKey();
    }

    public static function looksLikeSecretKey(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && str_starts_with($value, 'sk_');
    }

    public static function looksLikePublishableKey(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && str_starts_with($value, 'pk_');
    }
}
