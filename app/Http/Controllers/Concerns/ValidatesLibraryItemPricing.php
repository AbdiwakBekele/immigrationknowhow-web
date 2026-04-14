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
        $validated['currency'] = strtoupper((string) ($validated['currency'] ?? 'USD'));
        $validated['price'] = round((float) $validated['price'], 2);
        $validated['is_premium'] = $validated['price'] > 0;

        if ($validated['price'] < 0) {
            throw ValidationException::withMessages([
                'price' => 'Price cannot be negative.',
            ]);
        }

        return $validated;
    }
}
