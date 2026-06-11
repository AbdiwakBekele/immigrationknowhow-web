<?php

namespace Tests\Unit;

use App\Models\Ad;
use App\Models\User;
use App\Support\AdPostingPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdPostingPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_three_ads_are_free_then_standard_price_applies(): void
    {
        config(['ads.free_limit' => 3, 'ads.default_price_cents' => 999]);

        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'ad-pricing-test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertSame(3, AdPostingPricing::freeAdsRemaining($user->id));
        $this->assertSame(0, AdPostingPricing::priceCentsForNewAd($user->id));

        for ($i = 0; $i < 3; $i++) {
            Ad::query()->create([
                'user_id' => $user->id,
                'title' => "Test ad {$i}",
                'description' => 'Test',
                'cta_url' => 'https://example.com',
                'status' => 'draft',
                'price_cents' => 0,
                'currency' => 'USD',
            ]);
        }

        $this->assertSame(0, AdPostingPricing::freeAdsRemaining($user->id));
        $this->assertSame(999, AdPostingPricing::priceCentsForNewAd($user->id));
    }
}
