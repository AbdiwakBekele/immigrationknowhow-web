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

    public function test_library_item_falls_back_to_uuid_based_product_id(): void
    {
        config([
            'services.apple_iap.library_product_prefix' => 'com.immigrantknowhow.ikhapp.library',
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
