<?php

namespace Tests\Unit;

use App\Models\LibraryItem;
use App\Support\LibraryEbookPricing;
use Tests\TestCase;

class LibraryEbookPricingTest extends TestCase
{
    public function test_standard_price_defaults_to_499_cents(): void
    {
        config(['library.standard_price_cents' => 499]);

        $this->assertSame(499, LibraryEbookPricing::standardPriceCents());
        $this->assertSame('4.99', LibraryEbookPricing::standardPriceAmount());
    }

    public function test_paid_standard_title_qualifies_for_ebook_credit(): void
    {
        config(['library.standard_price_cents' => 499]);

        $item = new LibraryItem([
            'type' => 'ebook',
            'price' => 4.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
        ]);

        $this->assertTrue(LibraryEbookPricing::itemQualifiesForEbookCredit($item));
    }

    public function test_non_standard_price_does_not_qualify_for_ebook_credit(): void
    {
        config(['library.standard_price_cents' => 499]);

        $item = new LibraryItem([
            'type' => 'ebook',
            'price' => 9.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
        ]);

        $this->assertFalse(LibraryEbookPricing::itemQualifiesForEbookCredit($item));
    }
}
