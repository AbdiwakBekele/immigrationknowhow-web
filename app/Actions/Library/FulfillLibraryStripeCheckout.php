<?php

namespace App\Actions\Library;

use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

final class FulfillLibraryStripeCheckout
{
    /**
     * Record purchase after Stripe reports payment succeeded (success redirect or webhook).
     *
     * @return bool True when access was granted or was already granted.
     */
    public function __invoke(Session $session): bool
    {
        if (($session->metadata['app'] ?? '') !== 'library') {
            return false;
        }

        if ($session->payment_status !== 'paid') {
            return false;
        }

        $userId = (int) ($session->metadata['user_id'] ?? 0);
        if ($userId < 1) {
            return false;
        }

        $itemIds = [];
        $rawList = $session->metadata['library_item_ids'] ?? null;
        if (is_string($rawList) && trim($rawList) !== '') {
            $itemIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawList)))));
        }
        if ($itemIds === []) {
            $single = (int) ($session->metadata['library_item_id'] ?? 0);
            if ($single > 0) {
                $itemIds = [$single];
            }
        }

        if ($itemIds === []) {
            return false;
        }

        $items = LibraryItem::query()
            ->whereIn('id', $itemIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($items->count() !== count(array_unique($itemIds))) {
            return false;
        }

        $expectedCents = 0;

        foreach ($itemIds as $id) {
            $item = $items->get($id);
            if (! $item) {
                return false;
            }

            $requiresPaidAccess = $item->is_premium || ((float) ($item->price ?? 0) > 0);
            if (! $requiresPaidAccess) {
                return false;
            }

            $expectedCents += (int) round((float) $item->price * 100);
        }

        $paidCents = (int) ($session->amount_total ?? 0);
        if ($paidCents < 1 || abs($expectedCents - $paidCents) > 2) {
            return false;
        }

        return DB::transaction(function () use ($itemIds, $items, $userId, $session) {
            $paymentIntentId = $session->payment_intent;
            if (is_object($paymentIntentId) && isset($paymentIntentId->id)) {
                $paymentIntentId = $paymentIntentId->id;
            }
            $paymentIntentId = is_string($paymentIntentId) ? $paymentIntentId : null;

            $sessionCurrency = strtoupper((string) ($session->currency ?? 'USD'));

            foreach ($itemIds as $id) {
                $item = $items->get($id);
                if (! $item) {
                    return false;
                }

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
                    continue;
                }

                $access->update([
                    'purchased_at' => now(),
                    'purchase_amount' => round((float) $item->price, 2),
                    'purchase_currency' => strtoupper((string) ($item->currency ?? $sessionCurrency)),
                    'stripe_checkout_session_id' => $session->id,
                    'stripe_payment_intent_id' => $paymentIntentId,
                ]);
            }

            return true;
        });
    }
}
