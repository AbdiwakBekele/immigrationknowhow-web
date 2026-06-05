<?php

namespace Tests\Unit;

use App\Models\LibraryItem;
use App\Support\AppleIapConfig;
use Tests\TestCase;

class AppleIapConfigTest extends TestCase
{
    public function test_library_item_resolves_configured_apple_product_id(): void
    {
        $item = new LibraryItem([
            'apple_product_id' => 'com.example.custom.book',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->assertSame('com.example.custom.book', $item->appleProductId());
    }

    public function test_library_item_uses_env_ebook_product_id(): void
    {
        config([
            'services.apple_iap.library_ebook_product_id' => 'EBOOK_TO2026',
            'services.apple_iap.library_ebook_slug' => '',
        ]);

        $item = new LibraryItem([
            'apple_product_id' => null,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'slug' => 'any-paid-title',
        ]);

        $this->assertSame('EBOOK_TO2026', $item->appleProductId());
    }

    public function test_library_item_falls_back_to_uuid_based_product_id(): void
    {
        config([
            'services.apple_iap.library_product_prefix' => 'com.immigrantknowhow.ikhapp.library',
            'services.apple_iap.library_ebook_product_id' => '',
            'services.apple_iap.library_ebook_slug' => '',
        ]);

        $item = new LibraryItem([
            'apple_product_id' => null,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->assertSame(
            'com.immigrantknowhow.ikhapp.library.550e8400-e29b-41d4-a716-446655440000',
            $item->appleProductId()
        );
    }

    public function test_private_key_prefers_file_path_over_inline(): void
    {
        $keyDir = storage_path('app/apple');
        if (! is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }

        $keyFile = $keyDir.DIRECTORY_SEPARATOR.'test_apple_key.pem';
        file_put_contents($keyFile, "-----BEGIN PRIVATE KEY-----\nfrom-file\n-----END PRIVATE KEY-----");

        config([
            'services.apple_iap.private_key_path' => $keyFile,
            'services.apple_iap.private_key' => 'from-inline',
        ]);

        $this->assertSame("-----BEGIN PRIVATE KEY-----\nfrom-file\n-----END PRIVATE KEY-----", AppleIapConfig::privateKey());

        @unlink($keyFile);
    }

    public function test_apple_iap_configured_requires_credentials(): void
    {
        config([
            'services.apple_iap.bundle_id' => 'com.test.app',
            'services.apple_iap.issuer_id' => '',
            'services.apple_iap.key_id' => '',
            'services.apple_iap.private_key' => '',
        ]);

        $this->assertFalse(AppleIapConfig::configured());
    }
}
