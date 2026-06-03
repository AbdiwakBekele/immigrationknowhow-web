<?php

namespace App\Support;

final class AppleIapConfig
{
    public static function configured(): bool
    {
        return self::bundleId() !== ''
            && self::issuerId() !== ''
            && self::keyId() !== ''
            && self::privateKey() !== '';
    }

    public static function bundleId(): string
    {
        return trim((string) config('services.apple_iap.bundle_id', ''));
    }

    public static function issuerId(): string
    {
        return trim((string) config('services.apple_iap.issuer_id', ''));
    }

    public static function keyId(): string
    {
        return trim((string) config('services.apple_iap.key_id', ''));
    }

    /**
     * App Store Connect API private key (.p8 contents).
     */
    public static function privateKey(): string
    {
        $inline = trim((string) config('services.apple_iap.private_key', ''));
        if ($inline !== '') {
            return str_replace('\\n', "\n", $inline);
        }

        $path = trim((string) config('services.apple_iap.private_key_path', ''));
        if ($path === '' || ! is_readable($path)) {
            return '';
        }

        return trim((string) file_get_contents($path));
    }

    public static function useSandbox(): bool
    {
        return filter_var(config('services.apple_iap.sandbox', true), FILTER_VALIDATE_BOOL);
    }

    public static function apiBaseUrl(): string
    {
        return self::useSandbox()
            ? 'https://api.storekit-sandbox.itunes.apple.com'
            : 'https://api.storekit.itunes.apple.com';
    }

    public static function aiAssistantProductId(): string
    {
        return trim((string) config('services.apple_iap.ai_assistant_product_id', ''));
    }

    public static function defaultLibraryProductId(string $itemUuid): string
    {
        $prefix = trim((string) config('services.apple_iap.library_product_prefix', 'com.immigrantknowhow.ikhapp.library'));

        return $prefix.'.'.$itemUuid;
    }
}
