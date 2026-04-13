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

        $itemId = (int) ($session->metadata['library_item_id'] ?? 0);
        $userId = (int) ($session->metadata['user_id'] ?? 0);

        if ($itemId < 1 || $userId < 1) {
            return false;
        }

        $item = LibraryItem::query()
            ->whereKey($itemId)
            ->where('is_active', true)
            ->first();

        if (! $item || ! $item->is_premium) {
            return false;
        }

        return DB::transaction(function () use ($item, $userId, $session) {
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
                return true;
            }

            $amountTotal = $session->amount_total;
            $amount = $amountTotal !== null
                ? round(((int) $amountTotal) / 100, 2)
                : (float) $item->price;

            $currency = strtoupper((string) ($session->currency ?? $item->currency ?? 'USD'));

            $paymentIntentId = $session->payment_intent;
            if (is_object($paymentIntentId) && isset($paymentIntentId->id)) {
                $paymentIntentId = $paymentIntentId->id;
            }

            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => $amount,
                'purchase_currency' => $currency,
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
            ]);

            return true;
        });
    }
}
