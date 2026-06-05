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
     * Prefers APPLE_PRIVATE_KEY_PATH when set; falls back to APPLE_PRIVATE_KEY inline value.
     */
    public static function privateKey(): string
    {
        $path = trim((string) config('services.apple_iap.private_key_path', ''));
        if ($path !== '') {
            $resolvedPath = self::resolvePrivateKeyPath($path);
            if (is_readable($resolvedPath)) {
                return trim((string) file_get_contents($resolvedPath));
            }
        }

        $inline = trim((string) config('services.apple_iap.private_key', ''));
        if ($inline !== '') {
            return str_replace('\\n', "\n", $inline);
        }

        return '';
    }

    public static function privateKeyPath(): string
    {
        $path = trim((string) config('services.apple_iap.private_key_path', ''));

        return $path !== '' ? self::resolvePrivateKeyPath($path) : '';
    }

    private static function resolvePrivateKeyPath(string $path): string
    {
        if (is_readable($path)) {
            return $path;
        }

        $storageRelative = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($storageRelative, 'storage/')) {
            $storageRelative = substr($storageRelative, strlen('storage/'));
        }

        $candidates = [
            storage_path($storageRelative),
            storage_path('app/apple/'.basename($path)),
            base_path($path),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return $candidate;
            }
        }

        return $path;
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

    public static function libraryEbookProductId(): string
    {
        return trim((string) config('services.apple_iap.library_ebook_product_id', ''));
    }

    /**
     * When set, only this library item slug uses APPLE_LIBRARY_EBOOK_PRODUCT_ID.
     * Leave empty to apply the env product ID to any item without apple_product_id.
     */
    public static function libraryEbookSlug(): string
    {
        return trim((string) config('services.apple_iap.library_ebook_slug', ''));
    }

    public static function defaultLibraryProductId(string $itemUuid): string
    {
        $prefix = trim((string) config('services.apple_iap.library_product_prefix', 'com.immigrantknowhow.ikhapp.library'));

        return $prefix.'.'.$itemUuid;
    }
}
