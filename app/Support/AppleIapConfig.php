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

    /**
     * Safe diagnostics for logs (no secret values).
     *
     * @return array<string, bool|string>
     */
    public static function configurationDiagnostics(): array
    {
        $privateKeyPath = trim((string) config('services.apple_iap.private_key_path', ''));
        $resolvedPath = $privateKeyPath !== '' ? self::resolvePrivateKeyPath($privateKeyPath) : '';
        $inlineKey = trim((string) config('services.apple_iap.private_key', ''));

        return [
            'bundle_id_set' => self::bundleId() !== '',
            'issuer_id_set' => self::issuerId() !== '',
            'key_id_set' => self::keyId() !== '',
            'private_key_inline_set' => $inlineKey !== '',
            'private_key_path_configured' => $privateKeyPath !== '',
            'private_key_path_readable' => $resolvedPath !== '' && is_readable($resolvedPath),
            'private_key_resolved' => self::privateKey() !== '',
            'sandbox' => self::useSandbox(),
            'configured' => self::configured(),
        ];
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
        return self::libraryEbookCreditProductId();
    }

    /** Consumable IAP: one credit redeems for one library title at the standard price. */
    public static function libraryEbookCreditProductId(): string
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

    public static function providerProductPrefix(): string
    {
        return trim((string) config('services.apple_iap.provider_product_prefix', 'com.immigrantknowhow.ikhapp.provider'));
    }

    public static function providerMonthlyProductId(): string
    {
        return trim((string) config('services.apple_iap.provider_monthly_product_id', ''));
    }

    public static function providerYearlyProductId(): string
    {
        return trim((string) config('services.apple_iap.provider_yearly_product_id', ''));
    }

    /**
     * Resolve the App Store product ID for a provider subscription plan.
     * Strictly uses env monthly/yearly IDs (no fallback).
     */
    public static function providerProductIdForBillingCycle(?string $billingCycle, string $planUuid): string
    {
        $cycle = strtolower(trim((string) $billingCycle));

        if (in_array($cycle, ['year', 'yearly', 'annual'], true)) {
            $yearly = self::providerYearlyProductId();
            return $yearly;
        }

        if (in_array($cycle, ['month', 'monthly'], true)) {
            $monthly = self::providerMonthlyProductId();
            return $monthly;
        }

        return '';
    }

    public static function defaultProviderProductId(string $planUuid): string
    {
        return self::providerProductPrefix().'.'.$planUuid;
    }

    public static function videoProductPrefix(): string
    {
        return trim((string) config('services.apple_iap.video_product_prefix', 'com.immigrantknowhow.ikhapp.video'));
    }

    public static function defaultVideoProductId(string $videoUuid): string
    {
        return self::videoProductPrefix().'.'.$videoUuid;
    }

    public static function adPublishProductId(): string
    {
        return trim((string) config('services.apple_iap.ad_publish_product_id', ''));
    }

    public static function adPublishPriceCents(): int
    {
        return max(0, (int) config('ads.default_price_cents', 999));
    }
}
