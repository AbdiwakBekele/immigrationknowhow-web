<?php

namespace App\Actions\Advertiser;

use App\Models\Ad;
use App\Models\AdPayment;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

final class FulfillAdvertiserStripeCheckout
{
    public function __invoke(Session $session): bool
    {
        if (($session->metadata['app'] ?? '') !== 'advertiser_ad') {
            return false;
        }

        if ($session->payment_status !== 'paid') {
            return false;
        }

        $adId = (int) ($session->metadata['ad_id'] ?? 0);
        $userId = (int) ($session->metadata['user_id'] ?? 0);
        if ($adId < 1 || $userId < 1) {
            return false;
        }

        $ad = Ad::query()->whereKey($adId)->where('user_id', $userId)->first();
        if (! $ad || $ad->isSuspended()) {
            return false;
        }

        return DB::transaction(function () use ($ad, $userId, $session): bool {
            $paymentIntentId = $session->payment_intent;
            if (is_object($paymentIntentId) && isset($paymentIntentId->id)) {
                $paymentIntentId = $paymentIntentId->id;
            }

            $amountCents = (int) ($session->amount_total ?? $ad->price_cents);
            $currency = strtoupper((string) ($session->currency ?? $ad->currency ?? 'USD'));

            AdPayment::query()->updateOrCreate(
                ['stripe_checkout_session_id' => $session->id],
                [
                    'ad_id' => $ad->id,
                    'user_id' => $userId,
                    'amount_cents' => max(0, $amountCents),
                    'currency' => $currency,
                    'status' => 'paid',
                    'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
                    'paid_at' => now(),
                    'meta' => ['source' => 'stripe_checkout'],
                ],
            );

            $requireApproval = (bool) config('ads.require_admin_approval', true);

            if ($requireApproval) {
                $ad->update([
                    'status' => 'pending_approval',
                    'paid_at' => $ad->paid_at ?? now(),
                    'published_at' => null,
                    'last_paid_checkout_session_id' => $session->id,
                ]);
            } else {
                $ad->update([
                    'status' => 'published',
                    'paid_at' => $ad->paid_at ?? now(),
                    'published_at' => $ad->published_at ?? now(),
                    'last_paid_checkout_session_id' => $session->id,
                ]);
            }

            return true;
        });
    }
}
