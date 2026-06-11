<?php

namespace Tests\Unit;

use App\Support\AiAssistantPricing;
use Tests\TestCase;

class AiAssistantPricingTest extends TestCase
{
    public function test_monthly_price_is_9_99_usd(): void
    {
        $this->assertSame(999, AiAssistantPricing::monthlyPriceCents());
        $this->assertSame('9.99', AiAssistantPricing::monthlyPriceAmount());
        $this->assertSame('USD', AiAssistantPricing::currency());
    }
}
