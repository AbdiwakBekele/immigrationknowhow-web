<?php

namespace App\Actions\Library;

use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use Illuminate\Support\Facades\DB;

final class GrantLibraryItemAccess
{
    /**
     * @param  array{
     *   purchase_amount?: float|int|null,
     *   purchase_currency?: string|null,
     *   purchase_source?: string|null,
     *   apple_transaction_id?: string|null,
     *   apple_original_transaction_id?: string|null,
     *   stripe_checkout_session_id?: string|null,
     *   stripe_payment_intent_id?: string|null,
     * }  $purchaseMeta
     */
    public function __invoke(LibraryItem $item, int $userId, array $purchaseMeta = []): LibraryUserAccess
    {
        return DB::transaction(function () use ($item, $userId, $purchaseMeta) {
            /** @var LibraryUserAccess $access */
            $access = LibraryUserAccess::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'library_item_id' => $item->id,
                ],
                []
            );

            $access->refresh();

            if ($access->purchased_at !== null) {
                return $access;
            }

            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => array_key_exists('purchase_amount', $purchaseMeta)
                    ? $purchaseMeta['purchase_amount']
                    : ($item->price !== null ? round((float) $item->price, 2) : null),
                'purchase_currency' => strtoupper((string) ($purchaseMeta['purchase_currency'] ?? $item->currency ?? 'USD')),
                'purchase_source' => $purchaseMeta['purchase_source'] ?? null,
                'apple_transaction_id' => $purchaseMeta['apple_transaction_id'] ?? $access->apple_transaction_id,
                'apple_original_transaction_id' => $purchaseMeta['apple_original_transaction_id'] ?? $access->apple_original_transaction_id,
                'stripe_checkout_session_id' => $purchaseMeta['stripe_checkout_session_id'] ?? $access->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $purchaseMeta['stripe_payment_intent_id'] ?? $access->stripe_payment_intent_id,
            ]);

            return $access->fresh();
        });
    }
}
