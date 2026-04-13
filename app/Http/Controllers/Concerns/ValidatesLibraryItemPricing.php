<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\ValidationException;

trait ValidatesLibraryItemPricing
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function applyLibraryItemPricing(array $validated): array
    {
        $validated['is_premium'] = (bool) ($validated['is_premium'] ?? false);
        $validated['currency'] = strtoupper((string) ($validated['currency'] ?? 'USD'));
        $validated['price'] = round((float) $validated['price'], 2);

        if ($validated['is_premium'] && $validated['price'] < 0.01) {
            throw ValidationException::withMessages([
                'price' => 'One-time purchase items must have a price of at least 0.01.',
            ]);
        }

        return $validated;
    }
}
