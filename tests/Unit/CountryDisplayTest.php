<?php

namespace Tests\Unit;

use App\Support\CountryDisplay;
use Tests\TestCase;

class CountryDisplayTest extends TestCase
{
    public function test_normalizes_common_csv_country_strings(): void
    {
        $this->assertSame('US', CountryDisplay::normalizeForStorage('United States'));
        $this->assertSame('CA', CountryDisplay::normalizeForStorage('Canada'));
        $this->assertSame('GB', CountryDisplay::normalizeForStorage('Great Britain'));
        $this->assertSame('EU', CountryDisplay::normalizeForStorage('Europe'));
    }

    public function test_label_for_display_uses_readable_names(): void
    {
        $this->assertSame('United States', CountryDisplay::labelForDisplay('US'));
        $this->assertSame('Canada', CountryDisplay::labelForDisplay('Canada'));
    }

    public function test_storage_match_values_include_legacy_csv_strings(): void
    {
        $matches = CountryDisplay::storageMatchValues('US');

        $this->assertContains('US', $matches);
        $this->assertContains('United States', $matches);
    }
}
