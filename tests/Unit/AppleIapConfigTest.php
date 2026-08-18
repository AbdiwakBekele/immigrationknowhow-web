<?php

namespace Tests\Unit;

use App\Models\LibraryItem;
use App\Models\SubscriptionPlan;
use App\Support\AppleIapConfig;
use Tests\TestCase;

class AppleIapConfigTest extends TestCase
{
    public function test_library_item_returns_ebook_credit_product_for_standard_paid_title(): void
    {
        config([
            'library.standard_price_cents' => 599,
            'services.apple_iap.library_ebook_product_id' => 'com.example.ebook_credit',
        ]);

        $item = new LibraryItem([
            'type' => 'ebook',
            'price' => 5.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'slug' => 'paid-title',
        ]);

        $this->assertSame('com.example.ebook_credit', $item->appleProductId());
        $this->assertTrue($item->usesEbookCreditIap());
    }

    public function test_library_item_returns_null_when_price_is_not_standard(): void
    {
        config([
            'library.standard_price_cents' => 599,
            'services.apple_iap.library_ebook_product_id' => 'com.example.ebook_credit',
        ]);

        $item = new LibraryItem([
            'type' => 'ebook',
            'price' => 9.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->assertNull($item->appleProductId());
        $this->assertFalse($item->usesEbookCreditIap());
    }

    public function test_library_item_returns_null_when_ebook_credit_product_not_configured(): void
    {
        config([
            'library.standard_price_cents' => 599,
            'services.apple_iap.library_ebook_product_id' => '',
        ]);

        $item = new LibraryItem([
            'type' => 'ebook',
            'price' => 5.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->assertNull($item->appleProductId());
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

    public function test_provider_plan_uses_env_monthly_and_yearly_product_ids(): void
    {
        config([
            'services.apple_iap.provider_monthly_product_id' => 'com.immigrantknowhow.ikhapp.provider.serviceprofessional_monthly',
            'services.apple_iap.provider_yearly_product_id' => 'com.immigrantknowhow.ikhapp.provider.serviceprofessional_yearly',
        ]);

        $monthly = new SubscriptionPlan;
        $monthly->billing_cycle = 'monthly';
        $monthly->uuid = '550e8400-e29b-41d4-a716-446655440001';

        $yearly = new SubscriptionPlan;
        $yearly->billing_cycle = 'yearly';
        $yearly->uuid = '550e8400-e29b-41d4-a716-446655440002';

        $this->assertSame('com.immigrantknowhow.ikhapp.provider.serviceprofessional_monthly', $monthly->appleProductId());
        $this->assertSame('com.immigrantknowhow.ikhapp.provider.serviceprofessional_yearly', $yearly->appleProductId());
    }

    public function test_provider_plan_db_apple_product_id_overrides_env(): void
    {
        config([
            'services.apple_iap.provider_monthly_product_id' => 'com.immigrantknowhow.ikhapp.provider.serviceprofessional_monthly',
        ]);

        $plan = new SubscriptionPlan;
        $plan->billing_cycle = 'monthly';
        $plan->uuid = '550e8400-e29b-41d4-a716-446655440001';
        $plan->apple_product_id = 'com.custom.override';

        $this->assertSame('com.custom.override', $plan->appleProductId());
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
