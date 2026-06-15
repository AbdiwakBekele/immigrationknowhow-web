<?php

namespace Tests\Unit;

use App\Support\AiAssistantPricing;
use Tests\TestCase;

class AiAssistantPricingTest extends TestCase
{
    public function test_monthly_price_is_4_99_usd(): void
    {
        $this->assertSame(499, AiAssistantPricing::monthlyPriceCents());
        $this->assertSame('4.99', AiAssistantPricing::monthlyPriceAmount());
        $this->assertSame('USD', AiAssistantPricing::currency());
    }
}
